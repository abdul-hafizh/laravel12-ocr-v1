<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Libraries\SendTelegram;
use App\Services\Telegram\TelegramRouterService;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        \Log::info('TELEGRAM RAW BODY', [
            'payload' => $request->all(),
        ]);

        app(TelegramRouterService::class)->handle($request);

        return response()->json(['ok' => true]);
    }
}