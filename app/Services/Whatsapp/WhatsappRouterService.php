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

        $user = DB::table('dbo.users')
            ->where('is_active', 1)
            ->where('is_delete', 0)
            ->whereNotNull('phone')
            ->get()
            ->first(function ($user) use ($phone) {
                return $this->normalizePhone($user->phone) === $phone;
            });

        if (!$user) {
            \Log::info('WA IGNORED PHONE', [
                'phone' => $phone,
                'message' => $message,
            ]);

            SendSms::sendMessageWA(
                $phone,
                "Maaf, nomor Anda belum terdaftar di sistem.\n\n".
                "Silakan hubungi admin untuk mendaftarkan nomor WhatsApp Anda."
            );

            return;
        }

        $cabang = DB::table('dbo.master_cabangs')
            ->where('pic_user_id', $user->id)
            ->where('is_active', 1)
            ->first();

        $cmd = strtoupper(trim($message));

        if ($cmd === 'PING') {
            SendSms::sendMessageWA($phone, 'pong ✅ webhook aktif');
            return;
        }

        if (in_array($cmd, ['RESET', 'ULANG', 'CANCEL', 'BATAL'], true)) {
            DB::table('dbo.wa_sessions')->where('phone', $phone)->delete();

            SendSms::sendMessageWA($phone, $this->menuText($user->role_id));
            return;
        }

        $session = DB::table('dbo.wa_sessions')->where('phone', $phone)->first();

        if (!$session) {
            DB::table('dbo.wa_sessions')->insert([
                'phone' => $phone,
                'user_id' => $user->id,
                'cabang_id' => $cabang?->id,
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
                SendSms::sendMessageWA($phone, $this->menuText($user->role_id));
                return;
            }

            if (!$this->userCanAccessMenu($user->role_id, $menu)) {
                SendSms::sendMessageWA(
                    $phone,
                    "Maaf, Anda tidak memiliki akses ke menu tersebut.\n\n" .
                    $this->menuText($user->role_id)
                );
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

            default => SendSms::sendMessageWA($phone, $this->menuText($user->role_id)),
        };
    }

    private function userCanAccessMenu(?int $roleId, string $menuKey): bool
    {
        if (!$roleId) {
            return false;
        }

        return DB::table('dbo.role_whatsapp_menus')
            ->where('role_id', $roleId)
            ->where('menu_key', $menuKey)
            ->where('is_active', 1)
            ->exists();
    }

    private function menuText(?int $roleId = null): string
    {
        $menus = [
            'BMI' => '*1* BMI',
            'BIAYA_UMUM' => '*2* Biaya Umum',
            'BIAYA_TOKEN_LISTRIK' => '*3* Biaya Token Listrik',
            'BIAYA_KLIK_METER' => '*4* Biaya Klik Meter',
            'BIAYA_PART' => '*5* Biaya Part',
            'MAINTENANCE_MESIN' => '*6* Maintenance Mesin',
        ];

        if ($roleId) {
            $allowedMenus = DB::table('dbo.role_whatsapp_menus')
                ->where('role_id', $roleId)
                ->where('is_active', 1)
                ->pluck('menu_key')
                ->toArray();

            $menus = array_filter(
                $menus,
                fn ($label, $key) => in_array($key, $allowedMenus, true),
                ARRAY_FILTER_USE_BOTH
            );
        }

        if (empty($menus)) {
            return "Halo 👋\n\nAnda belum memiliki akses menu WhatsApp.\nSilakan hubungi admin.";
        }

        return
            "Halo 👋\n".
            "Silakan pilih menu:\n\n".
            implode("\n", $menus) . "\n\n".
            "Ketik angka menu.\n";
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