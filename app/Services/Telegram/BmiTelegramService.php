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

            if (!$user) {
                SendTelegram::sendMessage($chatId, "⚠️ Akun Telegram Anda belum terhubung ke user aktif.");
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
                "Menu BMI dipilih ✅\n\n" .
                "Halo {$user->name} 👋\n" .
                "ID: {$user->employee_id}\n\n" .
                "Kirim data sekalian pakai spasi:\n" .
                "LP BB TB\n" .
                "Contoh: 80 70 164\n\n" .
                "LP=Lingkar Pinggang(cm)\n" .
                "BB=Berat(kg)\n" .
                "TB=Tinggi(cm)"
            );
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_BMI_START_ERROR', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            SendTelegram::sendMessage($chatId, "Maaf, menu BMI sedang error.");
        }
    }

    public function handle(string|int $chatId, string $message, object $session): void
    {
        if ($this->handleGlobalCommand($chatId, $message)) {
            return;
        }

        if (($session->step ?? 'ASK_ALL') === 'ASK_ALL') {
            $this->handleMeasurement($chatId, $message, $session);
            return;
        }

        $this->start($chatId);
    }

    private function handleMeasurement(string|int $chatId, string $message, object $session): void
    {
        $parts = preg_split('/\s+/', trim($message));

        if (count($parts) !== 3) {
            SendTelegram::sendMessage($chatId, "Format belum sesuai.\nContoh: 80 70 164");
            return;
        }

        $lp = (int) filter_var($parts[0], FILTER_SANITIZE_NUMBER_INT);
        $bb = (float) str_replace(',', '.', (string) $parts[1]);
        $tb = (int) filter_var($parts[2], FILTER_SANITIZE_NUMBER_INT);

        if ($lp < 40 || $lp > 200) {
            SendTelegram::sendMessage($chatId, "LP tidak valid (40-200).\nContoh: 80 70 164");
            return;
        }

        if (!is_numeric($bb) || $bb < 20 || $bb > 300) {
            SendTelegram::sendMessage($chatId, "BB tidak valid (20-300).\nContoh: 80 70 164");
            return;
        }

        if ($tb < 100 || $tb > 250) {
            SendTelegram::sendMessage($chatId, "TB tidak valid (100-250).\nContoh: 80 70 164");
            return;
        }

        $employeeId = $session->employee_id ?? null;
        $employeeName = $session->employee_name ?? null;
        $gender = strtoupper((string) ($session->gender ?? ''));

        if (!$employeeId || !in_array($gender, ['L', 'P'], true)) {
            $this->resetToBmiLogin($chatId);
            SendTelegram::sendMessage($chatId, "Session BMI diperbarui ✅\n\nSilakan kirim data:\n80 70 164");
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
            SendTelegram::sendMessage($chatId, "⚠️ {$employeeName} sudah submit bulan ini.\n\nKetik MENU untuk kembali.");
            return;
        }

        DB::table('dbo.employee_measurements')->insert([
            'employee_id' => $employeeId,
            'employee_name' => $employeeName,
            'phone' => null,
            'waist_cm' => $lp,
            'weight_kg' => $bb,
            'height_cm' => $tb,
            'bmi' => $bmiH,
            'selisih' => $selisih,
            'ket' => $ket,
            'periode' => date('Y-m-01'),
            'created_at' => now(),
        ]);

        $totalMonth = DB::table('dbo.employee_measurements')
            ->where('employee_id', $employeeId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $this->resetToMenu($chatId);

        SendTelegram::sendMessage(
            $chatId,
            "✅ Data BMI tersimpan\n" .
            "Nama: {$employeeName}\n" .
            "ID: {$employeeId}\n" .
            "Gender: {$gender}\n" .
            "LP: {$lp} cm\n" .
            "BB: {$bb} kg\n" .
            "TB: {$tb} cm\n" .
            "BMI(H): {$bmiH}\n" .
            "Selisih: {$selisih}\n" .
            "Ket: {$ket}\n\n" .
            "Total submit bulan ini: {$totalMonth}\n\n" .
            "Ketik MENU untuk kembali."
        );
    }

    private function resetToBmiLogin(string|int $chatId): void
    {
        $session = DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->first();

        $user = $session?->user_id
            ? DB::table('dbo.users')->where('id', $session->user_id)->first()
            : null;

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
        return in_array($g, ['L', 'P'], true) ? $g : 'L';
    }

    private function bmiExcelH(string $gender, int $tbCm, int $lpCm): int
    {
        if ($lpCm <= 0) return 0;
        $val = strtoupper($gender) === 'P'
            ? 76 - (20 * $tbCm / $lpCm)
            : 64 - (20 * $tbCm / $lpCm);

        return (int) floor($val);
    }

    private function hitungSelisihExcel(string $gender, int $bmiH): int
    {
        if (strtoupper($gender) === 'P') {
            if ($bmiH > 31) return $bmiH - 31;
            if ($bmiH < 25) return 25 - $bmiH;
            return 0;
        }

        if ($bmiH > 24) return $bmiH - 24;
        if ($bmiH < 18) return 18 - $bmiH;
        return 0;
    }

    private function ketExcel(string $gender, int $bmiH): string
    {
        if (strtoupper($gender) === 'P') {
            if ($bmiH > 31) return 'gemuk';
            if ($bmiH < 25) return 'kurus';
            return 'normal';
        }

        if ($bmiH > 24) return 'gemuk';
        if ($bmiH < 18) return 'kurus';
        return 'normal';
    }
}