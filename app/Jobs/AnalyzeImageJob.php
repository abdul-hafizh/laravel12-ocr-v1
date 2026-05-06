<?php

namespace App\Jobs;

use App\Models\ImageScan;
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
                                'text' => $this->getPromptByType($scan->scan_type),
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

        $scan->update([
            'status' => 'success',
            'analysis_result' => $parsed ?: $json,
            'extracted_text' => $parsed['teks_terbaca'] ?? $text,
        ]);
    }

    private function getPromptByType(string $type): string
    {
        return match ($type) {
            'electricity' => 'Analisis gambar meteran listrik / kWh meter / token listrik ini dan kembalikan hanya JSON valid.

            Ambil informasi penting yang terlihat pada meter listrik seperti:
            - nilai kWh pada layar meter
            - nomor meter / nomor token yang tertulis pada meter
            - lokasi atau alamat pada watermark pojok kanan bawah
            - tanggal foto jika tersedia
            - informasi tambahan lain yang relevan

            Format:
            {
                "jenis_gambar": "token_listrik",
                "ringkasan": "",
                "teks_terbaca": "",
                "data_penting": {
                    "tanggal": null,
                    "kwh": null,
                    "nomor_meter": null,
                    "nomor_token": null,
                    "lokasi": null,
                    "alamat_lengkap": null,
                    "kecamatan": null,
                    "kota": null,
                    "provinsi": null
                },
                "confidence": 0
            }
            ',

            'online_receipt' => 'Analisis gambar struk online / invoice / bukti transaksi online ini dan kembalikan hanya JSON valid.
            Format:
            {
                "jenis_gambar": "struk_online",
                "ringkasan": "",
                "teks_terbaca": "",
                "data_penting": {
                    "tanggal": null,
                    "nama_toko": null,
                    "nama_pembeli": null,
                    "nomor_pesanan": null,
                    "nomor_referensi": null,
                    "total_pembayaran": null,
                    "metode_pembayaran": null,
                    "status_pembayaran": null
                },
                "confidence": 0
            }
            ',

            default => 'Analisis gambar mesin cetak / printer / fotocopy ini dan kembalikan hanya JSON valid.
            Format:
            {
            "jenis_gambar": "mesin_cetak",
            "ringkasan": "",
            "teks_terbaca": "",
            "data_penting": {
                "tanggal": null,
                "nama_mesin": null,
                "lokasi": null,
                "total_black_white_large": null,
                "total_black_white_small": null,
                "total_full_color_large": null,
                "total_full_color_small": null,
                "total_long_sheet": null,
                "total": null
            },
            "confidence": 0
            }
            ',
        };
    }

    public function failed(Throwable $exception): void
    {
        ImageScan::where('id', $this->scanId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
