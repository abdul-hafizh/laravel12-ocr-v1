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
            throw new \Exception(json_encode($response->json()));
        }

        $json = $response->json();
        $text = $json['output'][0]['content'][0]['text'] ?? null;

        $parsed = json_decode($text, true);

        // if (!is_array($parsed)) {
        //     throw new \Exception('Response OpenAI bukan JSON valid: ' . $text);
        // }

        // $parsed = ImageAnalysisPromptService::normalize($parsed, $scan->scan_type);

        $result = $parsed['data_penting'] ?? $parsed;

        Log::info('OPENAI_RESULT_BEFORE_SAVE', [
            'scan_id' => $scan->id,
            'result' => $result,
            'json_result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $scan->update([
            'status' => 'success',
            'analysis_result' => $parsed ?: $json,
            'extracted_text' => $parsed['teks_terbaca'] ?? $text,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        ImageScan::where('id', $this->scanId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
