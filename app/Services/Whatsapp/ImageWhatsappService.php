<?php

namespace App\Services\Whatsapp;

use App\Jobs\AnalyzeImageJob;
use App\Libraries\SendSms;
use App\Models\ImageScan;
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
                                'text' => $this->getValidationPrompt($scanType),
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

        if (!is_array($parsed)) {
            return [
                'valid' => false,
                'message' => 'Sistem tidak dapat membaca isi gambar dengan jelas.',
            ];
        }

        return [
            'valid' => (bool)($parsed['valid'] ?? false),
            'message' => $parsed['message'] ?? 'Data wajib tidak ditemukan.',
            'data' => $parsed['data'] ?? [],
        ];
    }

    private function getValidationPrompt(string $scanType): string
    {
        return match ($scanType) {
            'electricity' => '
            Validasi apakah gambar ini sesuai untuk menu TOKEN LISTRIK / KWH METER.

            Syarat valid:
            - Harus terlihat nomor token listrik ATAU nomor meter.
            - Harus terlihat nilai kWh / kwh / angka kWh pada meter.
            - Jika keduanya tidak ada, gambar tidak valid.

            Kembalikan hanya JSON valid:
            {
                "valid": true,
                "message": "",
                "data": {
                    "nomor_token": null,
                    "nomor_meter": null,
                    "kwh": null
                }
            }

            Jika tidak valid:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Nomor token/nomor meter dan kWh tidak ditemukan.",
                "data": {}
            }
            ',

            'online_receipt' => '
            Validasi apakah gambar ini sesuai untuk menu STRUK ONLINE / BIAYA UMUM / BIAYA PART.

            Syarat valid:
            - Harus terlihat nominal pembayaran / total pembayaran / jumlah uang.
            - Nominal bisa berbentuk Rp, IDR, total, subtotal, grand total, amount, atau angka pembayaran.
            - Jika nominal tidak ada, gambar tidak valid.

            Kembalikan hanya JSON valid:
            {
                "valid": true,
                "message": "",
                "data": {
                    "nominal": null
                }
            }

            Jika tidak valid:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Nominal pembayaran tidak ditemukan.",
                "data": {}
            }
            ',

            default => '
            Validasi apakah gambar ini sesuai untuk menu MESIN CETAK / KLIK METER / MAINTENANCE MESIN.

            Syarat valid:
            - Harus terlihat serial number atau informasi mesin.
            - Harus terlihat nama mesin yang berada di bawah / dekat serial number.
            - Jika nama mesin tidak ada, gambar tidak valid.

            Kembalikan hanya JSON valid:
            {
                "valid": true,
                "message": "",
                "data": {
                    "serial_number": null,
                    "nama_mesin": null
                }
            }

            Jika tidak valid:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Nama mesin di bawah serial number tidak ditemukan.",
                "data": {}
            }
            ',
        };
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