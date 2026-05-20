<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSms
{
    public static function sendMessageWA($to, $message)
    {
        $token = env('WABLAS_TOKEN');
        $secretKey = env('WABLAS_SECRET_KEY');
        $baseUrl = rtrim(env('WABLAS_BASE_URL', 'https://tegal.wablas.com'), '/');

        $to = self::normalizePhone($to);

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->post($baseUrl . '/api/send-message', [
            'phone' => $to,
            'message' => $message,
            'secret_key' => $secretKey,
        ]);

        Log::info('WABLAS_SEND_OUT', [
            'to' => $to,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->json();
    }

    public static function sendDocumentWA($to, $documentUrl, $caption = '')
    {
        $token = env('WABLAS_TOKEN');
        $secretKey = env('WABLAS_SECRET_KEY');
        $baseUrl = rtrim(env('WABLAS_BASE_URL', 'https://tegal.wablas.com'), '/');

        $to = self::normalizePhone($to);

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->post($baseUrl . '/api/send-document', [
            'phone' => $to,
            'document' => $documentUrl,
            'caption' => $caption,
            'secret_key' => $secretKey,
        ]);

        Log::info('WABLAS_SEND_DOCUMENT_OUT', [
            'to' => $to,
            'document' => $documentUrl,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->json();
    }

    private static function normalizePhone($phone): string
    {
        $phone = trim((string) $phone);

        $phone = str_replace([
            '+',
            ' ',
            '-',
            '(',
            ')'
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