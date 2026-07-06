<?php

namespace App\Services\Telegram;

use App\Libraries\SendTelegram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BmiTelegramService extends BaseTelegramService
{
    public function start(string|int $chatId): void
    {
        try {
            $user = $this->findActiveUserByChatId($chatId);

            if (!$user) {
                DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                    'menu' => null,
                    'step' => 'ASK_MENU',
                    'employee_id' => null,
                    'employee_name' => null,
                    'gender' => null,
                    'updated_at' => now(),
                ]);

                SendTelegram::sendMessage(
                    $chatId,
                    "⚠️ Akun Telegram Anda belum terdaftar / tidak aktif.\n\n" .
                    "Silakan hubungi admin agar Telegram Anda didaftarkan."
                );

                return;
            }

            $gender = $this->extractGender($user);

            DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                'menu' => 'BMI',
                'step' => 'ASK_ALL',
                'employee_id' => $user->employee_id,
                'employee_name' => $user->name,
                'gender' => $gender,
                'updated_at' => now(),
            ]);

            SendTelegram::sendMessage(
                $chatId,
                "Menu <b>BMI</b> dipilih ✅\n\n" .
                "Halo <b>{$user->name}</b> 👋\n" .
                "ID: <b>{$user->employee_id}</b>\n\n" .
                "Kirim data <b>sekalian</b> pakai spasi:\n" .
                "<b>LP BB TB</b>\n" .
                "Contoh: <b>80 70 164</b>\n\n" .
                "LP=Lingkar Pinggang(cm)\n" .
                "BB=Berat(kg)\n" .
                "TB=Tinggi(cm)"
            );
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_BMI_START_ERROR', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            SendTelegram::sendMessage(
                $chatId,
                "Maaf, menu BMI sedang error. Silakan coba lagi atau hubungi admin."
            );
        }
    }

    public function handle(string|int $chatId, string $message, object $session, array $payload = []): void
    {
        $cmd = strtoupper(trim($message));

        if (in_array($cmd, ['MENU', 'RESET', 'ULANG', 'CANCEL', 'BATAL', '/START'], true)) {
            $this->resetToMenu($chatId);

            SendTelegram::sendMessage(
                $chatId,
                "Silakan pilih menu kembali dengan mengetik <b>MENU</b>."
            );

            return;
        }

        $step = $session->step ?? 'ASK_ALL';

        if ($step === 'ASK_ALL') {
            $this->handleMeasurement($chatId, $message, $session);
            return;
        }

        $this->start($chatId);
    }

    private function handleMeasurement(string|int $chatId, string $message, object $session): void
    {
        $parts = preg_split('/\s+/', trim($message));

        if (count($parts) !== 3) {
            SendTelegram::sendMessage(
                $chatId,
                "Format belum sesuai.\n" .
                "Kirim dengan spasi:\n" .
                "<b>LP BB TB</b>\n" .
                "Contoh: <b>80 70 164</b>"
            );
            return;
        }

        $lp = (int) filter_var($parts[0], FILTER_SANITIZE_NUMBER_INT);
        $bb = (float) str_replace(',', '.', (string) $parts[1]);
        $tb = (int) filter_var($parts[2], FILTER_SANITIZE_NUMBER_INT);

        if ($lp < 40 || $lp > 200) {
            SendTelegram::sendMessage($chatId, "LP tidak valid (40-200).\nContoh: <b>80 70 164</b>");
            return;
        }

        if (!is_numeric($bb) || $bb < 20 || $bb > 300) {
            SendTelegram::sendMessage($chatId, "BB tidak valid (20-300).\nContoh: <b>80 70 164</b>");
            return;
        }

        if ($tb < 100 || $tb > 250) {
            SendTelegram::sendMessage($chatId, "TB tidak valid (100-250).\nContoh: <b>80 70 164</b>");
            return;
        }

        $employeeId = $session->employee_id ?? null;
        $employeeName = $session->employee_name ?? null;
        $gender = strtoupper((string) ($session->gender ?? ''));

        if (!$employeeId || ($gender !== 'L' && $gender !== 'P')) {
            $this->resetToBmiInput($chatId);

            SendTelegram::sendMessage(
                $chatId,
                "Session BMI diperbarui ✅\n\n" .
                "Silakan kirim data:\n" .
                "<b>LP BB TB</b>\n" .
                "Contoh: <b>80 70 164</b>"
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
            $this->resetToMenu($chatId);

            SendTelegram::sendMessage(
                $chatId,
                "⚠️ Maaf, <b>{$employeeName}</b> (ID: <b>{$employeeId}</b>) sudah submit <b>bulan ini</b>.\n" .
                "Tidak bisa submit lagi.\n\n" .
                "Kalau ada koreksi, hubungi admin ya.\n\n" .
                "Ketik <b>MENU</b> untuk kembali ke menu."
            );
            return;
        }

        try {
            DB::table('dbo.employee_measurements')->insert([
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'telegram_chat_id' => $chatId,
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
            Log::error('TELEGRAM_MEAS_INSERT_ERR', [
                'chat_id' => $chatId,
                'err' => $e->getMessage(),
            ]);

            SendTelegram::sendMessage(
                $chatId,
                "Gagal simpan data karena DB error.\n" .
                "Coba lagi ya."
            );
            return;
        }

        $totalMonth = DB::table('dbo.employee_measurements')
            ->where('employee_id', $employeeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $this->resetToMenu($chatId);

        $nama = $employeeName ?: $employeeId;

        SendTelegram::sendMessage(
            $chatId,
            "✅ <b>Data BMI tersimpan</b>\n" .
            "Nama: <b>{$nama}</b>\n" .
            "ID: <b>{$employeeId}</b>\n" .
            "Gender: <b>{$gender}</b>\n" .
            "LP: <b>{$lp} cm</b>\n" .
            "BB: <b>{$bb} kg</b>\n" .
            "TB: <b>{$tb} cm</b>\n" .
            "BMI(H): <b>{$bmiH}</b>\n" .
            "Selisih: <b>{$selisih}</b>\n" .
            "Ket: <b>{$ket}</b>\n\n" .
            "Total submit bulan ini: <b>{$totalMonth}</b>\n\n" .
            "Ketik <b>MENU</b> untuk kembali ke menu."
        );
    }

    private function resetToBmiInput(string|int $chatId): void
    {
        $user = $this->findActiveUserByChatId($chatId);

        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
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

    private function resetToMenu(string|int $chatId): void
    {
        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
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

    private function findActiveUserByChatId(string|int $chatId): ?object
    {
        return DB::table('dbo.telegram_users as tu')
            ->join('dbo.users as u', 'u.id', '=', 'tu.user_id')
            ->where('tu.telegram_chat_id', $chatId)
            ->where('tu.is_active', 1)
            ->where('u.is_active', 1)
            ->where('u.is_delete', 0)
            ->select('u.*')
            ->first();
    }
}