<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegram
{
    public static function sendMessage(string|int $chatId, string $message): array
    {
        $token = config('services.telegram.bot_token');

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        Log::info('TELEGRAM_SEND_OUT', [
            'chat_id' => $chatId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->json() ?? [];
    }

    public static function sendContactRequest(string|int $chatId): array
    {
        $token = config('services.telegram.bot_token');

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => "👋 Selamat datang.\n\n"
                . "Sebelum menggunakan bot ini, silakan lakukan verifikasi terlebih dahulu.\n\n"
                . "Tekan tombol <b>Bagikan Nomor Telepon</b> di bawah ini.",
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'keyboard' => [
                    [
                        [
                            'text' => 'Bagikan Nomor Telepon',
                            'request_contact' => true,
                        ]
                    ]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
        ]);

        Log::info('TELEGRAM_SEND_CONTACT_REQUEST', [
            'chat_id' => $chatId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->json() ?? [];
    }

    public static function removeKeyboard(string|int $chatId, string $message): array
    {
        $token = config('services.telegram.bot_token');

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'remove_keyboard' => true,
            ],
        ]);

        Log::info('TELEGRAM_REMOVE_KEYBOARD', [
            'chat_id' => $chatId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->json() ?? [];
    }
}