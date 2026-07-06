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
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.4-mini'),
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => ImageAnalysisPromptService::getPrompt($scan->scan_type),
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => "data:{$mimeType};base64,{$base64}",
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('OpenAI error: ' . $response->body());
        }

        $json = $response->json();

        $text =
            $json['output_text']
            ?? $json['output'][0]['content'][0]['text']
            ?? null;

        $parsed = null;

        if ($text) {
            $cleanText = trim($text);

            $cleanText = preg_replace('/^```json\s*/', '', $cleanText);
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

        if ($scan->scan_type === 'part_maintenance') {
            $oldAnalysis = is_array($scan->analysis_result)
                ? $scan->analysis_result
                : [];

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
            'scan_type' => $scan->scan_type,
            'analysis_result' => $parsed,
        ]);

        if ($scan->scan_type === 'electricity') {
            $dataPenting = $parsed['data_penting'] ?? [];

            $nomorMeter = $dataPenting['nomor_meter'] ?? null;
            $kwh = $dataPenting['kwh'] ?? null;

            $nomorMeterClean = $nomorMeter
                ? preg_replace('/[^0-9]/', '', (string) $nomorMeter)
                : null;

            $meterExists = false;

            if ($nomorMeterClean) {
                $meterExists = MasterTokenListrik::where('nomor_meter', $nomorMeterClean)
                    ->where('is_active', true)
                    ->exists();
            }

            if (!$meterExists) {
                $scan->update([
                    'status' => 'success',
                    'analysis_result' => $parsed,
                    'extracted_text' => $parsed['teks_terbaca']
                        ?? $parsed['data']['teks_terbaca']
                        ?? $parsed['data_penting']['teks_terbaca']
                        ?? $text,
                    'error_message' => null,
                ]);

                $telegramChatId = $scan->analysis_result['data_penting']['telegram_chat_id'] ?? null;

                $telegramSession = null;

                if ($telegramChatId) {
                    $telegramSession = DB::table('dbo.telegram_sessions')
                        ->where('chat_id', $telegramChatId)
                        ->first();
                }

                if (!$telegramSession) {
                    $telegramSession = DB::table('dbo.telegram_sessions')
                        ->where('user_id', $scan->user_id)
                        ->latest('updated_at')
                        ->first();
                }

                if ($telegramSession) {
                    DB::table('dbo.telegram_sessions')
                        ->where('chat_id', $telegramSession->chat_id)
                        ->update([
                            'step' => 'ASK_ELECTRICITY_CORRECTION',
                            'last_image_scan_id' => $scan->id,
                            'updated_at' => now(),
                        ]);

                    SendTelegram::sendMessage(
                        $telegramSession->chat_id,
                        "⚠️ Nomor meter token listrik tidak ditemukan di database.\n\n" .
                        "Apakah benar data ini?\n\n" .
                        "Nomor Meter: " . ($nomorMeterClean ?: '-') . "\n" .
                        "kWh: " . ($kwh ?: '-') . "\n\n" .
                        "Silakan kirim data yang benar dengan format:\n" .
                        "nomor meter, kWh\n\n" .
                        "Contoh:\n12345678901, 25.60"
                    );

                    if ($nomorMeterClean) {
                        SendTelegram::sendMessage(
                            $telegramSession->chat_id,
                            $nomorMeterClean
                        );
                    }

                    return;
                }

                return;
            }
        }

        if (in_array($scan->scan_type, ['printer', 'cea', 'asaba'], true)) {
            $dataPenting = $parsed['data_penting'] ?? [];

            $serialNumber = $dataPenting['serial_number'] ?? null;

            $serialNumberClean = $serialNumber
                ? strtoupper(trim((string) $serialNumber))
                : null;

            $mesin = null;

            if ($serialNumberClean) {
                $mesin = MasterMesin::where('serial_number', $serialNumberClean)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$mesin) {
                $scan->update([
                    'status' => 'success',
                    'analysis_result' => $parsed,
                    'extracted_text' => $parsed['teks_terbaca']
                        ?? $parsed['data']['teks_terbaca']
                        ?? $parsed['data_penting']['teks_terbaca']
                        ?? $text,
                    'error_message' => null,
                ]);

                $namaMenu = match ($scan->scan_type) {
                    'printer' => 'Mesin Samafitro',
                    'cea' => 'Mesin CEA',
                    'asaba' => 'Mesin Asaba',
                    default => 'Mesin',
                };

                $telegramChatId = $scan->analysis_result['data_penting']['telegram_chat_id'] ?? null;

                $telegramSession = null;

                if ($telegramChatId) {
                    $telegramSession = DB::table('dbo.telegram_sessions')
                        ->where('chat_id', $telegramChatId)
                        ->first();
                }

                if (!$telegramSession) {
                    $telegramSession = DB::table('dbo.telegram_sessions')
                        ->where('user_id', $scan->user_id)
                        ->latest('updated_at')
                        ->first();
                }

                if ($telegramSession) {
                    DB::table('dbo.telegram_sessions')
                        ->where('chat_id', $telegramSession->chat_id)
                        ->update([
                            'step' => 'ASK_MACHINE_SERIAL_CORRECTION',
                            'last_image_scan_id' => $scan->id,
                            'updated_at' => now(),
                        ]);

                    SendTelegram::sendMessage(
                        $telegramSession->chat_id,
                        "⚠️ Serial number {$namaMenu} tidak ditemukan di database.\n\n" .
                        "Apakah benar serial number ini?\n\n" .
                        "Serial Number: " . ($serialNumberClean ?: '-') . "\n\n" .
                        "Silakan kirim serial number yang benar.\n\n" .
                        "Contoh:\nABC123456"
                    );

                    if ($serialNumberClean) {
                        SendTelegram::sendMessage($telegramSession->chat_id, $serialNumberClean);
                    }
                }

                return;
            }

            $parsed['data_penting']['master_mesin'] = [
                'id' => $mesin->id,
                'nama_mesin' => $mesin->nama_mesin,
                'serial_number' => $mesin->serial_number,
            ];
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