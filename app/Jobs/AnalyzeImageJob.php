<?php

namespace App\Jobs;

use App\Libraries\SendTelegram;
use App\Services\ImageAnalysisPromptService;
use App\Models\ImageScan;
use App\Models\MasterTokenListrik;
use App\Models\MasterMesin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyzeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $scanId)
    {
    }

    public function handle(): void
    {
        $scan = ImageScan::findOrFail($this->scanId);

        $text = null;
        $json = null;
        $parsed = null;
        $scanType = $scan->scan_type;

        if ($scan->status === 'success' && is_array($scan->analysis_result)) {
            $parsed = $scan->analysis_result;
            goto processing_data; 
        }

        $scan->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        $fullPath = Storage::disk('public')->path($scan->image_path);

        if (!file_exists($fullPath)) {
            throw new \Exception('File gambar tidak ditemukan: ' . $scan->image_path);
        }

        $base64 = base64_encode(file_get_contents($fullPath));
        $mimeType = $scan->mime_type ?: mime_content_type($fullPath);

        $response = Http::timeout(120)
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => ImageAnalysisPromptService::getPrompt($scanType),
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64}"
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('OpenAI error: ' . $response->body());
        }

        $json = $response->json();
        $text = $response->json('choices.0.message.content');

        processing_data:

        if ($text) {
            $cleanText = trim($text);
            $cleanText = preg_replace('/^```json\s*/i', '', $cleanText);
            $cleanText = preg_replace('/^```\s*/', '', $cleanText);
            $cleanText = preg_replace('/```$/', '', $cleanText);

            $parsed = json_decode(trim($cleanText), true);
        }

        if (!is_array($parsed)) {
            $parsed = [
                'valid' => false,
                'message' => 'Response OpenAI bukan JSON valid',
                'raw_text' => $text,
                'raw_response' => $json,
            ];
        }

        if ($scanType === 'part_maintenance') {
            $oldAnalysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
            $oldData = $oldAnalysis['data_penting'] ?? [];
            $newData = $parsed['data_penting'] ?? [];

            $nominalChat = $this->cleanNominal($oldData['nominal_chat'] ?? null);
            $nominalGambar = $this->cleanNominal($newData['nominal_gambar'] ?? null);
            $nominalFinal = $nominalChat ?: $nominalGambar;

            $parsed['data_penting'] = array_merge($newData, [
                'nominal_chat' => $nominalChat,
                'nominal_gambar' => $nominalGambar,
                'nominal_final' => $nominalFinal,
                'sumber_nominal' => $nominalChat ? 'chat' : ($nominalGambar ? 'gambar' : null),
            ]);
        }
        
        Log::info('OPENAI_RESULT_BEFORE_SAVE', [
            'scan_id' => $scan->id,
            'scan_type' => $scanType,
            'analysis_result' => $parsed,
        ]);

        if ($scanType === 'electricity') {
            $nomorMeter = preg_replace('/[^0-9]/', '', (string) ($parsed['data']['nomor_meter'] ?? ''));
            $kwh = $parsed['data']['kwh'] ?? null;

            $isManual = $parsed['data_penting']['is_manual_correction'] ?? false;

            if ($isManual) {
                $nomorMeter = $parsed['data_penting']['nomor_meter'] ?? $nomorMeter;
                $kwh = $parsed['data_penting']['kwh'] ?? $kwh;
            }

            $masterToken = null;
            if ($nomorMeter) {
                $masterToken = MasterTokenListrik::where('nomor_meter', $nomorMeter)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$isManual && (!$nomorMeter || !$masterToken || !$kwh || !$this->isValidKwh($kwh))) {
                $scan->update([
                    'status' => 'failed',
                    'error_message' => 'Gagal memproses data listrik otomatis.',
                ]);
                return;
            }

            if (!isset($parsed['data_penting'])) {
                $parsed['data_penting'] = [];
            }

            $parsed['data_penting']['nomor_meter'] = $nomorMeter;
            $parsed['data_penting']['kwh'] = $this->normalizeKwh($kwh);
            if ($masterToken) {
                $parsed['data_penting']['master_token_listrik'] = [
                    'id' => $masterToken->id,
                    'nomor_meter' => $masterToken->nomor_meter,
                ];
            }
        }

        if (in_array($scanType, ['printer', 'cea', 'asaba'], true)) {
            $serialNumber = trim((string) ($parsed['data']['serial_number'] ?? ''));
            $isManual = $parsed['data_penting']['is_manual_correction'] ?? false;

            if ($isManual) {
                $serialNumber = $parsed['data_penting']['serial_number'] ?? $serialNumber;
            }

            $serialNumberClean = preg_replace('/[^a-zA-Z0-9]/', '', $serialNumber);

            $mesin = null;
            if ($serialNumberClean !== '') {
                $mesin = MasterMesin::where('serial_number', $serialNumber)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$isManual && !$mesin) {
                $scan->update([
                    'status' => 'failed',
                    'error_message' => 'Serial number tidak ditemukan di database saat pemrosesan antrean.',
                ]);

                $telegramSession = DB::table('dbo.telegram_sessions')
                    ->where('last_image_scan_id', $scan->id)
                    ->first();

                if ($telegramSession) {
                    SendTelegram::sendMessage(
                        $telegramSession->chat_id,
                        "❌ Gagal memproses data mesin secara otomatis."
                    );
                }
                return;
            }

            if ($mesin) {
                $parsed['data_penting']['master_mesin'] = [
                    'id' => $mesin->id,
                    'nama_mesin' => $mesin->nama_mesin,
                    'serial_number' => $mesin->serial_number,
                ];
                
                $scan->master_mesin_id = $mesin->id;
            }
        }

        $scan->update([
            'status' => 'success',
            'analysis_result' => $parsed,
            'extracted_text' => $parsed['teks_terbaca']
                ?? $parsed['data']['teks_terbaca']
                ?? $parsed['data_penting']['teks_terbaca']
                ?? $text,
            'error_message' => null,
        ]);
    }

    private function cleanNominal($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $nominal = preg_replace('/[^0-9]/', '', (string) $value);

        return $nominal !== '' ? (int) $nominal : null;
    }

    public function failed(Throwable $exception): void
    {
        ImageScan::where('id', $this->scanId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('ANALYZE_IMAGE_JOB_FAILED', [
            'scan_id' => $this->scanId,
            'error' => $exception->getMessage(),
        ]);
    }
}