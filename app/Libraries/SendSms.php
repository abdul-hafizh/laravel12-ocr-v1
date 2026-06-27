<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSms
{
    public static function sendMessageWA(string $to, string $message): array
    {
        $to = self::normalizePhone($to);

        $response = Http::withHeaders([
            'Authorization' => config('services.wablas.token'),
        ])->post(
            rtrim(config('services.wablas.base_url'), '/') . '/api/send-message',
            [
                'phone'      => $to,
                'message'    => $message,
                'secret_key' => config('services.wablas.secret_key'),
            ]
        );

        Log::info('WABLAS_SEND_OUT', [
            'to'     => $to,
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return $response->json() ?? [];
    }

    public static function sendDocumentWA(string $to, string $documentUrl, string $caption = ''): array
    {
        $to = self::normalizePhone($to);

        $response = Http::withHeaders([
            'Authorization' => config('services.wablas.token'),
        ])->post(
            rtrim(config('services.wablas.base_url'), '/') . '/api/send-document',
            [
                'phone'      => $to,
                'document'   => $documentUrl,
                'caption'    => $caption,
                'secret_key' => config('services.wablas.secret_key'),
            ]
        );

        Log::info('WABLAS_SEND_DOCUMENT_OUT', [
            'to'       => $to,
            'document' => $documentUrl,
            'status'   => $response->status(),
            'body'     => $response->body(),
        ]);

        return $response->json() ?? [];
    }

    private static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        $phone = str_replace([
            '+',
            ' ',
            '-',
            '(',
            ')',
        ], '', $phone);

        if (preg_match('/^0\d+$/', $phone)) {
            $phone = preg_replace('/^0/', '62', $phone);
        }

        if (preg_match('/^8\d+$/', $phone)) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}