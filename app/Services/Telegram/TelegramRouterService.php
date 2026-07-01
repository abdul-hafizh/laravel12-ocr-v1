<?php

namespace App\Services\Telegram;

use App\Libraries\SendTelegram;
use App\Services\Telegram\ImageTelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelegramRouterService
{
    public function handle(Request $request): void
    {
        $message = $request->input('message');

        if (!$message) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $telegramUserId = $message['from']['id'] ?? null;
        $text = trim($message['text'] ?? '');

        if (!$chatId) {
            return;
        }

        $mapping = DB::table('dbo.telegram_users')
            ->where('telegram_chat_id', $chatId)
            ->where('telegram_user_id', $telegramUserId)
            ->where('is_active', 1)
            ->first();

        if (!$mapping) {
            SendTelegram::sendMessage(
                $chatId,
                "Maaf, akun Telegram Anda belum terdaftar di sistem.\n\n" .
                "Telegram ID Anda: {$telegramUserId}\n" .
                "Chat ID Anda: {$chatId}\n\n" .
                "Silakan hubungi admin."
            );
            return;
        }

        $user = DB::table('dbo.users')
            ->where('id', $mapping->user_id)
            ->where('is_active', 1)
            ->where('is_delete', 0)
            ->first();

        if (!$user) {
            SendTelegram::sendMessage($chatId, 'User tidak aktif di sistem.');
            return;
        }

        $cabang = DB::table('dbo.master_cabangs as c')
            ->join('dbo.master_cabang_user as mcu', 'mcu.master_cabang_id', '=', 'c.id')
            ->where('mcu.user_id', $user->id)
            ->where('c.is_active', 1)
            ->select('c.*')
            ->first();

        $cmd = strtoupper($text);

        if (in_array($cmd, ['MENU', 'RESET', 'ULANG', 'BATAL', '/START'], true)) {
            DB::table('dbo.telegram_sessions')->updateOrInsert(
                ['chat_id' => $chatId],
                [
                    'telegram_user_id' => $telegramUserId,
                    'user_id' => $user->id,
                    'cabang_id' => $cabang?->id,
                    'menu' => null,
                    'scan_type' => null,
                    'step' => 'ASK_MENU',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            SendTelegram::sendMessage(
                $chatId,
                "Halo {$user->name} 👋\n\n" . $this->menuText($user->role_id)
            );
            return;
        }

        $session = DB::table('dbo.telegram_sessions')
            ->where('chat_id', $chatId)
            ->first();

        if (!$session) {
            DB::table('dbo.telegram_sessions')->insert([
                'chat_id' => $chatId,
                'telegram_user_id' => $telegramUserId,
                'user_id' => $user->id,
                'cabang_id' => $cabang?->id,
                'menu' => null,
                'scan_type' => null,
                'step' => 'ASK_MENU',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $session = DB::table('dbo.telegram_sessions')
                ->where('chat_id', $chatId)
                ->first();
        }

        if (($session->step ?? '') === 'ASK_IMAGE') {
            app(ImageTelegramService::class)->handle($chatId, $text, $session, $request->all());
            return;
        }

        if (($session->step ?? '') === 'ASK_MENU') {
            $menu = $this->detectMenu($text);

            if (!$menu) {
                SendTelegram::sendMessage($chatId, $this->menuText($user->role_id));
                return;
            }

            if (!$this->userCanAccessMenu($user->role_id, $menu)) {
                SendTelegram::sendMessage(
                    $chatId,
                    "Maaf, Anda tidak memiliki akses ke menu tersebut.\n\n" .
                    $this->menuText($user->role_id)
                );
                return;
            }

            if ($menu === 'BMI') {
                // nanti kita buat BmiTelegramService
                SendTelegram::sendMessage($chatId, "Menu BMI Telegram belum diaktifkan.");
                return;
            }

            if ($menu === 'BIAYA_UMUM') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'online_receipt');
                return;
            }

            if ($menu === 'BIAYA_TOKEN_LISTRIK') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'electricity');
                return;
            }

            if ($menu === 'BIAYA_KLIK_METER') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'printer');
                return;
            }

            if ($menu === 'MESIN_CEA') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'cea');
                return;
            }

            if ($menu === 'ASABA') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'asaba');
                return;
            }

            if ($menu === 'BIAYA_PART') {
                app(ImageTelegramService::class)->startWithMachineSelection(
                    chatId: $chatId,
                    menu: $menu,
                    scanType: 'part_maintenance'
                );
                return;
            }

            if ($menu === 'MAINTENANCE_MESIN') {
                app(ImageTelegramService::class)->startWithMachineSelection(
                    chatId: $chatId,
                    menu: $menu,
                    scanType: 'part_maintenance'
                );
                return;
            }
        }

        SendTelegram::sendMessage(
            $chatId,
            "Ketik MENU untuk kembali ke menu utama."
        );
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
            'BIAYA_KLIK_METER' => '*4* Mesin Samafitro',
            'MESIN_CEA' => '*5* Mesin CEA',
            'ASABA' => '*6* Mesin Asaba',
            'BIAYA_PART' => '*7* Biaya Part',
            'MAINTENANCE_MESIN' => '*8* Maintenance Mesin',
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
            '5', 'MESIN CEA', 'CEA' => 'MESIN_CEA',
            '6', 'ASABA', 'MESIN ASABA' => 'ASABA',
            '7', 'PART', 'BIAYA PART' => 'BIAYA_PART',
            '8', 'MAINTENANCE', 'MAINTENANCE MESIN' => 'MAINTENANCE_MESIN',
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