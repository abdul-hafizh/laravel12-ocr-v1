<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Libraries\SendSms;
use App\Services\Whatsapp\WhatsappRouterService;

class WablasWebhookController extends Controller
{
    public function handle(Request $request)
    {
        \Log::info('WABLAS RAW BODY', [
            'raw' => $request->getContent(),
            'all' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        app(WhatsappRouterService::class)->handle($request);

        return response()->json(['ok' => true]);
    }
    
}