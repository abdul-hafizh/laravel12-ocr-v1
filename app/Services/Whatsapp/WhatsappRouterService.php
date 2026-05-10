<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Libraries\SendSms;

class WhatsappRouterService
{
    public function handle(Request $request): void
    {
        $data = $request->all();

        $phone = $data['phone'] ?? null;
        $message = trim((string)($data['message'] ?? ''));
        $messageType = $data['messageType'] ?? null;
        $imageUrl = $data['url'] ?? null;

        if (!$phone) {
            return;
        }

        if ($message === '' && $messageType !== 'image' && !$imageUrl) {
            return;
        }

        $phone = $this->normalizePhone($phone);

        $allowedPhones = [
            '6281314031553',
        ];

        if (!in_array($phone, $allowedPhones, true)) {
            \Log::info('WA IGNORED PHONE', [
                'phone' => $phone,
                'message' => $message,
            ]);

            return;
        }

        $cmd = strtoupper(trim($message));

        if ($cmd === 'PING') {
            SendSms::sendMessageWA($phone, 'pong ✅ webhook aktif');
            return;
        }

        if (in_array($cmd, ['RESET', 'ULANG', 'CANCEL', 'BATAL'], true)) {
            DB::table('dbo.wa_sessions')->where('phone', $phone)->delete();

            SendSms::sendMessageWA($phone, $this->menuText());
            return;
        }

        $session = DB::table('dbo.wa_sessions')->where('phone', $phone)->first();

        if (!$session) {
            DB::table('dbo.wa_sessions')->insert([
                'phone' => $phone,
                'menu' => null,
                'step' => 'ASK_MENU',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $session = DB::table('dbo.wa_sessions')
                ->where('phone', $phone)
                ->first();
        }

        if (($session->step ?? '') === 'ASK_MENU') {
            $menu = $this->detectMenu($message);

            if (!$menu) {
                SendSms::sendMessageWA($phone, $this->menuText());
                return;
            }

            if ($menu === 'BMI') {
                app(BmiWhatsappService::class)->start($phone);
                return;
            }

            if ($menu === 'BIAYA_UMUM') {
                app(ImageWhatsappService::class)->start($phone, $menu, 'online_receipt');
                return;
            }

            if ($menu === 'BIAYA_TOKEN_LISTRIK') {
                app(ImageWhatsappService::class)->start($phone, $menu, 'electricity');
                return;
            }

            if ($menu === 'BIAYA_KLIK_METER') {
                app(ImageWhatsappService::class)->start($phone, $menu, 'printer');
                return;
            }

            if ($menu === 'BIAYA_PART') {
                app(ImageWhatsappService::class)->start($phone, $menu, 'online_receipt');
                return;
            }

            if ($menu === 'MAINTENANCE_MESIN') {
                app(ImageWhatsappService::class)->start($phone, $menu, 'printer');
                return;
            }
        }

        match ($session->menu) {
            'BMI' => app(BmiWhatsappService::class)->handle($phone, $message, $session),

            'BIAYA_TOKEN_LISTRIK',
            'BIAYA_KLIK_METER',
            'MAINTENANCE_MESIN',
            'BIAYA_PART',
            'BIAYA_UMUM' => app(ImageWhatsappService::class)->handle($phone, $message, $session, $data),

            default => SendSms::sendMessageWA($phone, $this->menuText()),
        };
    }

    private function menuText(): string
    {
        return
            "Halo 👋\n".
            "Silakan pilih menu:\n\n".
            "*1* BMI\n".
            "*2* Biaya Umum\n".
            "*3* Biaya Token Listrik\n".
            "*4* Biaya Klik Meter\n".
            "*5* Biaya Part\n".
            "*6* Maintenance Mesin\n\n".
            "Ketik angka menu.\n".
            "Contoh: *1*";
    }

    private function detectMenu(string $message): ?string
    {
        return match (strtoupper(trim($message))) {
            '1', 'BMI' => 'BMI',
            '2', 'BIAYA UMUM' => 'BIAYA_UMUM',
            '3', 'TOKEN LISTRIK', 'BIAYA TOKEN LISTRIK' => 'BIAYA_TOKEN_LISTRIK',
            '4', 'KLIK METER', 'BIAYA KLIK METER' => 'BIAYA_KLIK_METER',
            '5', 'PART', 'BIAYA PART' => 'BIAYA_PART',
            '6', 'MAINTENANCE', 'MAINTENANCE MESIN' => 'MAINTENANCE_MESIN',
            default => null,
        };
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = str_replace(['+', ' ', '-', '(', ')'], '', $phone);

        if (preg_match('/^0\d+$/', $phone)) {
            $phone = preg_replace('/^0/', '62', $phone);
        }

        if (preg_match('/^8\d+$/', $phone)) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}