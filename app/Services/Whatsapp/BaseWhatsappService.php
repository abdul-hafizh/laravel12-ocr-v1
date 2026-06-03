<?php

namespace App\Services\Whatsapp;

use App\Libraries\SendSms;
use Illuminate\Support\Facades\DB;

class BaseWhatsappService
{
    protected function handleGlobalCommand(string $phone, string $message): bool
    {
        $cmd = strtoupper(trim($message));

        if (!in_array($cmd, [
            'MENU',
            'BATAL',
            'ULANG',
            'RESET',
            'CANCEL'
        ], true)) {
            return false;
        }

        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'menu' => null,
            'step' => 'ASK_MENU',
            'employee_id' => null,
            'employee_name' => null,
            'gender' => null,
            'scan_type' => null,
            'updated_at' => now(),
        ]);

        $user = DB::table('dbo.users')
            ->where('is_active', 1)
            ->where('is_delete', 0)
            ->whereNotNull('phone')
            ->get()
            ->first(function ($user) use ($phone) {
                return $this->normalizePhone($user->phone) === $this->normalizePhone($phone);
            });

        SendSms::sendMessageWA(
            $phone,
            "Kembali ke menu utama ✅\n\n" . $this->menuText($user?->role_id)
        );

        return true;
    }

    protected function menuText(?int $roleId = null): string
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
            implode("\n", $menus)."\n\n".
            "Ketik angka menu.\n";
    }

    protected function normalizePhone(string $phone): string
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