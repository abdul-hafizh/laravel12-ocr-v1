<?php

namespace App\Services\Telegram;

use App\Libraries\SendTelegram;
use Illuminate\Support\Facades\DB;

class BaseTelegramService
{
    protected function handleGlobalCommand(string|int $chatId, string $message): bool
    {
        $cmd = strtoupper(trim($message));

        if (!in_array($cmd, [
            'MENU',
            'BATAL',
            'ULANG',
            'RESET',
            'CANCEL',
            '/START',
        ], true)) {
            return false;
        }

        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
            'menu' => null,
            'step' => 'ASK_MENU',
            'employee_id' => null,
            'employee_name' => null,
            'gender' => null,
            'scan_type' => null,
            'master_mesin_id' => null,
            'master_mesin_part_id' => null,
            'last_image_scan_id' => null,
            'updated_at' => now(),
        ]);

        $session = DB::table('dbo.telegram_sessions')
            ->where('chat_id', $chatId)
            ->first();

        $user = null;

        if ($session?->user_id) {
            $user = DB::table('dbo.users')
                ->where('id', $session->user_id)
                ->where('is_active', 1)
                ->where('is_delete', 0)
                ->first();
        }

        SendTelegram::sendMessage(
            $chatId,
            "Kembali ke menu utama ✅\n\n" . $this->menuText($user?->role_id)
        );

        return true;
    }

    protected function menuText(?int $roleId = null): string
    {
        $menus = [
            'BMI' => '1. BMI',
            'BIAYA_UMUM' => '2. Biaya Umum',
            'BIAYA_TOKEN_LISTRIK' => '3. Biaya Token Listrik',
            'BIAYA_KLIK_METER' => '4. Mesin Samafitro',
            'MESIN_CEA' => '5. Mesin CEA',
            'ASABA' => '6. Asaba',
            'BIAYA_PART' => '7. Biaya Part',
            'MAINTENANCE_MESIN' => '8. Maintenance Mesin',
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
            return "Halo 👋\n\nAnda belum memiliki akses menu Telegram.\nSilakan hubungi admin.";
        }

        return
            "Halo 👋\n" .
            "Silakan pilih menu:\n\n" .
            implode("\n", $menus) . "\n\n" .
            "Ketik angka menu.";
    }
}