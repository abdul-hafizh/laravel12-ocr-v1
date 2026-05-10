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

        SendSms::sendMessageWA(
            $phone,
            "Kembali ke menu utama ✅\n\n".$this->menuText()
        );

        return true;
    }

    protected function menuText(): string
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
}