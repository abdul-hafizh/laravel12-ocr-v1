<?php

namespace App\Services\Whatsapp;

use App\Jobs\AnalyzeImageJob;
use App\Libraries\SendSms;
use App\Models\ImageScan;
use App\Models\MasterMesin;
use App\Services\ImageAnalysisPromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageWhatsappService
{
    public function start(string $phone, string $menu, string $scanType): void
    {
        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'menu' => $menu,
            'scan_type' => $scanType,
            'step' => 'ASK_IMAGE',
            'updated_at' => now(),
        ]);

        SendSms::sendMessageWA(
            $phone,
            "Menu " . $menu . " dipilih ✅\n\n" .
            "Silakan kirim gambar/foto untuk dianalisis."
        );
    }

    public function handle(string $phone, string $message, object $session, array $payload): void
    {
        $imageUrl =
            $payload['url']
            ?? $payload['image']
            ?? $payload['media_url']
            ?? $payload['media']
            ?? $payload['file']
            ?? null;

        if (($payload['messageType'] ?? null) !== 'image' || !$imageUrl) {
            SendSms::sendMessageWA(
                $phone,
                "Silakan kirim gambar/foto, bukan teks.\n\n" .
                "Ketik *ulang* untuk kembali ke menu."
            );
            return;
        }

        $scanType = $session->scan_type ?? 'printer';

        try {
            $response = Http::timeout(60)->get($imageUrl);

            if ($response->failed()) {
                SendSms::sendMessageWA($phone, "Gagal mengambil gambar dari WhatsApp.");
                return;
            }

            $contentType = $response->header('Content-Type') ?: 'image/jpeg';

            /**
             * VALIDASI GAMBAR SEBELUM DISIMPAN
             */
            $validation = $this->validateImageByScanType(
                scanType: $scanType,
                imageBody: $response->body(),
                mimeType: $contentType
            );

            if ($scanType === 'printer') {
                $serialNumber = $validation['data']['serial_number'] ?? null;

                if (!$serialNumber) {
                    SendSms::sendMessageWA(
                        $phone,
                        "❌ Serial number tidak ditemukan pada gambar.\n\nSilakan kirim gambar counter mesin yang menampilkan serial number."
                    );
                    return;
                }

                $mesin = MasterMesin::where('serial_number', $serialNumber)
                    ->where('is_active', true)
                    ->first();

                if (!$mesin) {
                    SendSms::sendMessageWA(
                        $phone,
                        "❌ Serial number *{$serialNumber}* tidak ditemukan di Master Mesin.\n\nSilakan daftarkan mesin terlebih dahulu."
                    );
                    return;
                }

                $bw = (int) ($validation['data']['total_black_white'] ?? 0);
                $color = (int) ($validation['data']['total_color'] ?? 0);
                $longSheet = (int) ($validation['data']['total_long_sheet'] ?? 0);

                $validation['data']['master_mesin'] = [
                    'id' => $mesin->id,
                    'nama_mesin' => $mesin->nama_mesin,
                    'serial_number' => $mesin->serial_number,
                    'harga_bw' => (int) $mesin->harga_bw,
                    'harga_color' => (int) $mesin->harga_color,
                    'harga_long_sheet' => (int) $mesin->harga_long_sheet,
                ];

                $validation['data']['perhitungan'] = [
                    'bw' => [
                        'qty' => $bw,
                        'harga' => (int) $mesin->harga_bw,
                        'subtotal' => $bw * (int) $mesin->harga_bw,
                    ],
                    'color' => [
                        'qty' => $color,
                        'harga' => (int) $mesin->harga_color,
                        'subtotal' => $color * (int) $mesin->harga_color,
                    ],
                    'long_sheet' => [
                        'qty' => $longSheet,
                        'harga' => (int) $mesin->harga_long_sheet,
                        'subtotal' => $longSheet * (int) $mesin->harga_long_sheet,
                    ],
                    'total' =>
                        ($bw * (int) $mesin->harga_bw) +
                        ($color * (int) $mesin->harga_color) +
                        ($longSheet * (int) $mesin->harga_long_sheet),
                ];
            }

            if (!($validation['valid'] ?? false)) {
                SendSms::sendMessageWA(
                    $phone,
                    "❌ Gambar tidak sesuai dengan menu yang dipilih.\n\n" .
                    "Alasan: " . ($validation['message'] ?? 'Data wajib tidak ditemukan.') . "\n\n" .
                    "Silakan kirim gambar yang sesuai.\n" .
                    "Ketik *ulang* untuk kembali ke menu."
                );

                return;
            }

            $extension = $this->extensionFromMime($contentType);

            $filename = 'wa_' . $phone . '_' . time() . '.' . $extension;
            $path = 'image-scans/' . $filename;

            Storage::disk('public')->put($path, $response->body());

            $scan = ImageScan::create([
                'user_id' => $session->user_id ?? null,
                'cabang_id' => $session->cabang_id ?? null,
                'scan_type' => $scanType,
                'image_path' => $path,
                'original_filename' => $filename,
                'mime_type' => $contentType,
                'status' => 'pending',
            ]);

            AnalyzeImageJob::dispatch($scan->id);

            DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                'menu' => null,
                'scan_type' => null,
                'step' => 'ASK_MENU',
                'updated_at' => now(),
            ]);

            $namaCabang = null;

            if (!empty($session->cabang_id)) {
                $namaCabang = DB::table('dbo.master_cabangs')
                    ->where('id', $session->cabang_id)
                    ->value('nama_cabang');
            }

            SendSms::sendMessageWA(
                $phone,
                "✅ Gambar berhasil diterima.\n" .
                "Sedang dianalisis oleh sistem.\n\n" .
                "ID Scan: *{$scan->id}*\n" .
                "Cabang: *" . ($namaCabang ?: 'Belum terdeteksi') . "*\n\n" .
                "Silakan cek hasilnya di website.\n\n" .
                "Ketik *ulang* untuk kembali ke menu."
            );
        } catch (\Throwable $e) {
            Log::error('WA_IMAGE_UPLOAD_ERR', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "Terjadi error saat memproses gambar.\n" .
                "Silakan coba kirim ulang."
            );
        }
    }

    private function validateImageByScanType(string $scanType, string $imageBody, string $mimeType): array
    {
        $base64 = base64_encode($imageBody);

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
                                'text' => ImageAnalysisPromptService::getPrompt($scanType),
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
            throw new \Exception('Validasi gambar gagal: ' . $response->body());
        }

        $json = $response->json();
        $text = $json['output'][0]['content'][0]['text'] ?? null;

        $parsed = json_decode($text, true);

        // if (is_array($parsed)) {
        //     $parsed = ImageAnalysisPromptService::normalize($parsed, $scanType);
        // }

        if (
            isset($parsed['data']['nominal']) &&
            !is_numeric($parsed['data']['nominal'])
        ) {
            $parsed['data']['nominal'] = (int) preg_replace(
                '/[^0-9]/',
                '',
                (string) $parsed['data']['nominal']
            );
        }

        if (!is_array($parsed)) {
            return [
                'valid' => false,
                'message' => 'Sistem tidak dapat membaca isi gambar dengan jelas.',
            ];
        }

        return [
            'valid' => (bool)($parsed['valid'] ?? false),
            'message' => $parsed['message'] ?? 'Data wajib tidak ditemukan.',
            'data' => $parsed['data_penting'] ?? [],
        ];
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}