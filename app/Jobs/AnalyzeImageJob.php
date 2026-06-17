<?php

namespace App\Jobs;

use App\Models\ImageScan;
use App\Services\ImageAnalysisPromptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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