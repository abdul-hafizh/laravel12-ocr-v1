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
}