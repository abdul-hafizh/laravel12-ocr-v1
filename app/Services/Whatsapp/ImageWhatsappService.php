<?php

namespace App\Services\Whatsapp;

use App\Jobs\AnalyzeImageJob;
use App\Libraries\SendSms;
use App\Models\ImageScan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImageWhatsappService
{
    public function start(string $phone, string $menu, string $scanType): void
    {
        \DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'menu' => $menu,
            'scan_type' => $scanType,
            'step' => 'ASK_IMAGE',
            'updated_at' => now(),
        ]);

        SendSms::sendMessageWA(
            $phone,
            "Menu dipilih ✅\n\n".
            "Silakan kirim gambar/foto untuk dianalisis."
        );
    }

    public function handle(string $phone, string $message, object $session, array $payload): void
    {
        $imageUrl = $payload['image'] 
            ?? $payload['url'] 
            ?? $payload['media'] 
            ?? $payload['file'] 
            ?? null;

        if (!$imageUrl) {
            SendSms::sendMessageWA(
                $phone,
                "Silakan kirim gambar/foto, bukan teks.\n\n".
                "Ketik *ULANG* untuk kembali ke menu."
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
            $extension = $this->extensionFromMime($contentType);

            $filename = 'wa_' . $phone . '_' . time() . '.' . $extension;
            $path = 'image-scans/' . $filename;

            Storage::disk('public')->put($path, $response->body());

            $scan = ImageScan::create([
                'scan_type' => $scanType,
                'image_path' => $path,
                'original_filename' => $filename,
                'mime_type' => $contentType,
                'status' => 'pending',
            ]);

            AnalyzeImageJob::dispatch($scan->id);

            \DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                'menu' => null,
                'scan_type' => null,
                'step' => 'ASK_MENU',
                'updated_at' => now(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "✅ Gambar berhasil diterima.\n".
                "Sedang dianalisis oleh sistem.\n\n".
                "ID Scan: *{$scan->id}*\n".
                "Silakan cek hasilnya di website.\n\n".
                "Ketik *ULANG* untuk kembali ke menu."
            );
        } catch (\Throwable $e) {
            \Log::error('WA_IMAGE_UPLOAD_ERR', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "Terjadi error saat memproses gambar.\n".
                "Silakan coba kirim ulang."
            );
        }
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