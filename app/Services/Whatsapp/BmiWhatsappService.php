<?php

namespace App\Services\Whatsapp;

use App\Libraries\SendSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class BmiWhatsappService extends BaseWhatsappService
{
    public function start(string $phone): void
    {
        try {
            $user = $this->findActiveUserByPhone($phone);

            if (! $user) {
                DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                    'menu' => null,
                    'step' => 'ASK_MENU',
                    'employee_id' => null,
                    'employee_name' => null,
                    'gender' => null,
                    'updated_at' => now(),
                ]);

                SendSms::sendMessageWA(
                    $phone,
                    "⚠️ Nomor WhatsApp Anda belum terdaftar / tidak aktif.\n\n".
                    "Silakan hubungi admin agar nomor WA Anda didaftarkan di master user."
                );

                return;
            }

            $gender = $this->extractGender($user);

            DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                'menu' => 'BMI',
                'step' => 'ASK_ALL',
                'employee_id' => $user->employee_id,
                'employee_name' => $user->name,
                'gender' => $gender,
                'updated_at' => now(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "Menu *BMI* dipilih ✅\n\n".
                "Halo *{$user->name}* 👋\n".
                "ID: *{$user->employee_id}*\n\n".
                "Kirim data *sekalian* pakai spasi:\n".
                "*LP BB TB*\n".
                "Contoh: *80 70 164*\n\n".
                "LP=Lingkar Pinggang(cm)\n".
                "BB=Berat(kg)\n".
                "TB=Tinggi(cm)"
            );
        } catch (\Throwable $e) {
            \Log::error('BMI_START_ERROR', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "Maaf, menu BMI sedang error. Silakan coba lagi atau hubungi admin."
            );
        }
    }

    public function handle(string $phone, string $message, object $session): void
    {
        if ($this->handleGlobalCommand($phone, $message)) {
            return;
        }

        $step = $session->step ?? 'ASK_ALL';

        if ($step === 'ASK_ALL') {
            $this->handleMeasurement($phone, $message, $session);
            return;
        }

        $this->start($phone);
    }

    private function handleMeasurement(string $phone, string $message, object $session): void
    {
        $parts = preg_split('/\s+/', trim($message));

        if (count($parts) !== 3) {
            SendSms::sendMessageWA(
                $phone,
                "Format belum sesuai.\n".
                "Kirim dengan spasi:\n".
                "*LP BB TB*\n".
                "Contoh: *80 70 164*"
            );
            return;
        }

        $lp = (int) filter_var($parts[0], FILTER_SANITIZE_NUMBER_INT);
        $bb = (float) str_replace(',', '.', (string) $parts[1]);
        $tb = (int) filter_var($parts[2], FILTER_SANITIZE_NUMBER_INT);

        if ($lp < 40 || $lp > 200) {
            SendSms::sendMessageWA($phone, "LP tidak valid (40-200).\nContoh: *80 70 164*");
            return;
        }

        if (!is_numeric($bb) || $bb < 20 || $bb > 300) {
            SendSms::sendMessageWA($phone, "BB tidak valid (20-300).\nContoh: *80 70 164*");
            return;
        }

        if ($tb < 100 || $tb > 250) {
            SendSms::sendMessageWA($phone, "TB tidak valid (100-250).\nContoh: *80 70 164*");
            return;
        }

        $employeeId = $session->employee_id ?? null;
        $employeeName = $session->employee_name ?? null;
        $gender = strtoupper((string) ($session->gender ?? ''));

        if (!$employeeId || ($gender !== 'L' && $gender !== 'P')) {
            $this->resetToBmiLogin($phone);

            SendSms::sendMessageWA(
                $phone,
                "Session BMI diperbarui ✅\n\n".
                "Silakan kirim data:\n".
                "*LP BB TB*\n".
                "Contoh: *80 70 164*"
            );
            return;
        }

        $bmiH = $this->bmiExcelH($gender, $tb, $lp);
        $selisih = $this->hitungSelisihExcel($gender, $bmiH);
        $ket = $this->ketExcel($gender, $bmiH);

        $already = DB::table('dbo.employee_measurements')
            ->where('employee_id', $employeeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->exists();

        if ($already) {
            $this->resetToMenu($phone);

            SendSms::sendMessageWA(
                $phone,
                "⚠️ Maaf, *{$employeeName}* (ID: *{$employeeId}*) sudah submit *bulan ini*.\n".
                "Tidak bisa submit lagi.\n\n".
                "Kalau ada koreksi, hubungi admin ya.\n\n".
                "Ketik *ULANG* untuk kembali ke menu."
            );
            return;
        }

        try {
            DB::table('dbo.employee_measurements')->insert([
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'phone' => $phone,
                'waist_cm' => $lp,
                'weight_kg' => $bb,
                'height_cm' => $tb,
                'bmi' => $bmiH,
                'selisih' => $selisih,
                'ket' => $ket,
                'periode' => date('Y-m-01'),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MEAS_INSERT_ERR', [
                'err' => $e->getMessage(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "Gagal simpan data karena DB error.\n".
                "Coba lagi ya."
            );
            return;
        }

        $totalMonth = DB::table('dbo.employee_measurements')
            ->where('employee_id', $employeeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $this->resetToMenu($phone);

        $nama = $employeeName ?: $employeeId;

        SendSms::sendMessageWA(
            $phone,
            "✅ *Data BMI tersimpan*\n".
            "Nama: *{$nama}*\n".
            "ID: *{$employeeId}*\n".
            "Gender: *{$gender}*\n".
            "LP: *{$lp} cm*\n".
            "BB: *{$bb} kg*\n".
            "TB: *{$tb} cm*\n".
            "BMI(H): *{$bmiH}*\n".
            "Selisih: *{$selisih}*\n".
            "Ket: *{$ket}*\n\n".
            "Total submit bulan ini: *{$totalMonth}*\n\n".
            "Ketik *ULANG* untuk kembali ke menu."
        );
    }

    private function resetToBmiLogin(string $phone): void
    {
        $user = $this->findActiveUserByPhone($phone);

        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'menu' => 'BMI',
            'step' => 'ASK_ALL',
            'employee_id' => $user?->employee_id,
            'employee_name' => $user?->name,
            'gender' => $user ? $this->extractGender($user) : null,
            'temp_waist' => null,
            'temp_weight' => null,
            'temp_height' => null,
            'updated_at' => now(),
        ]);
    }

    private function resetToMenu(string $phone): void
    {
        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'menu' => null,
            'step' => 'ASK_MENU',
            'employee_id' => null,
            'employee_name' => null,
            'gender' => null,
            'temp_waist' => null,
            'temp_weight' => null,
            'temp_height' => null,
            'updated_at' => now(),
        ]);
    }

    private function extractGender(object $user): string
    {
        $g = strtoupper(trim((string) ($user->gender ?? '')));

        if ($g === 'L' || $g === 'P') {
            return $g;
        }

        return 'L';
    }

    private function bmiExcelH(string $gender, int $tbCm, int $lpCm): int
    {
        $gender = strtoupper(trim($gender));

        if ($lpCm <= 0) {
            return 0;
        }

        if ($gender === 'P') {
            $val = 76 - (20 * $tbCm / $lpCm);
        } else {
            $val = 64 - (20 * $tbCm / $lpCm);
        }

        return (int) floor($val);
    }

    private function hitungSelisihExcel(string $gender, int $bmiH): int
    {
        $gender = strtoupper(trim($gender));

        if ($gender === 'P') {
            if ($bmiH > 31) {
                return $bmiH - 31;
            }

            if ($bmiH < 25) {
                return 25 - $bmiH;
            }

            return 0;
        }

        if ($bmiH > 24) {
            return $bmiH - 24;
        }

        if ($bmiH < 18) {
            return 18 - $bmiH;
        }

        return 0;
    }

    private function ketExcel(string $gender, int $bmiH): string
    {
        $gender = strtoupper(trim($gender));

        if ($gender === 'P') {
            if ($bmiH > 31) {
                return 'gemuk';
            }

            if ($bmiH < 25) {
                return 'kurus';
            }

            return 'normal';
        }

        if ($bmiH > 24) {
            return 'gemuk';
        }

        if ($bmiH < 18) {
            return 'kurus';
        }

        return 'normal';
    }

    private function findActiveUserByPhone(string $phone): ?object
    {
        $phone = $this->normalizePhone($phone);

        $users = DB::table('dbo.users')
            ->where('is_active', 1)
            ->where('is_delete', 0)
            ->get();

        foreach ($users as $user) {
            if ($this->normalizePhone($user->phone ?? '') === $phone) {
                return $user;
            }
        }

        return null;
    }
}