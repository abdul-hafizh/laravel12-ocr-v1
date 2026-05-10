<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSms
{
    public static function sendMessageWA($to, $message)
    {
        $token = env('WABLAS_TOKEN', false);
        $baseUrl = rtrim(env('WABLAS_BASE_URL'), '/');

        $to = self::normalizePhone($to);

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->post($baseUrl . '/api/send-message', [
            'phone' => $to,
            'message' => $message,
        ]);

        Log::info('WABLAS_SEND_OUT', [
            'to' => $to,
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

        // 08xxx -> 628xxx
        if (preg_match('/^0\d+$/', $phone)) {
            $phone = preg_replace('/^0/', '62', $phone);
        }

        // 8xxx -> 628xxx
        if (preg_match('/^8\d+$/', $phone)) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}