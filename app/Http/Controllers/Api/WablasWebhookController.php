<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Libraries\SendSms;

class WablasWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();

        // Payload Wablas (sesuaikan kalau key beda)
        $phone   = $data['phone'] ?? null;
        $message = trim((string)($data['message'] ?? ''));

        if (!$phone || $message === '') {
            return response()->json(['ok' => true]);
        }

        // =========================
        // Normalisasi PHONE (key session harus konsisten)
        // =========================
        $phone = $this->normalizePhone($phone);

        // =========================
        // Command cepat
        // =========================
        $cmd = strtoupper(trim($message));
        if (in_array($cmd, ['RESET', 'ULANG', 'CANCEL', 'BATAL'], true)) {
            $this->resetSession($phone);
            SendSms::sendMessageWA(
                $phone,
                "Oke bre ✅ Session di-reset.\n".
                "Login dulu:\n".
                "*EMPLOYEEID PASSWORD*\n".
                "Contoh: *SPY-0025 123456*"
            );
            return response()->json(['ok' => true]);
        }

        // =========================
        // Get / Init session (DB default: dbAPK)
        // =========================
        $session = DB::table('dbo.wa_sessions')->where('phone', $phone)->first();
        if (!$session) {
            DB::table('dbo.wa_sessions')->insert([
                'phone' => $phone,
                'step' => 'ASK_ID',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $session = DB::table('dbo.wa_sessions')->where('phone', $phone)->first();
        }

        $step = $session->step ?? 'ASK_ID';

        // =========================
        // Debug
        // =========================
        if (strtolower($message) === 'ping') {
            SendSms::sendMessageWA($phone, "pong ✅ webhook lu udah nyangkut bre");
            return response()->json(['ok' => true]);
        }

        // =========================
        // Kalau user kirim EmployeeID (atau EmployeeID + password) kapanpun
        // -> paksa balik ASK_ID (biar bisa login ulang)
        // =========================
        if ($this->looksLikeEmployeeId($message)) {
            DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                'step' => 'ASK_ID',
                'updated_at' => now(),
            ]);
            $step = 'ASK_ID';
        }

        // =========================
        // STEP 1: ASK_ID (LOGIN: employeeid + password)
        // format: "SPY-0025 password"
        // bypass password: SNAPY12
        // =========================
        if ($step === 'ASK_ID') {

            $parts = preg_split('/\s+/', trim($message), 2);

            if (count($parts) < 2) {
                SendSms::sendMessageWA(
                    $phone,
                    "Login dulu bre.\n".
                    "Kirim format:\n".
                    "*EMPLOYEEID PASSWORD*\n".
                    "Contoh: *SPY-0025 123456*"
                );
                return response()->json(['ok' => true]);
            }

            $employeeId = strtoupper(trim($parts[0]));
            $passPlain  = trim($parts[1]);

            $useBypass = ($passPlain === 'SNAPY12');
            $passMd5   = strtoupper(md5($passPlain)); // sama persis dengan PHP lama

            try {
                $q = DB::table('w_user')
                    ->whereRaw("UPPER(LTRIM(RTRIM(employeeid))) = ?", [$employeeId])
                    ->where('isActive', 'Y')
                    ->where('isDelete', 'N');
            
                if (!$useBypass) {
                    $q->whereRaw("UPPER(LTRIM(RTRIM([password]))) = ?", [$passMd5]);
                }
            
                $user = $q->first();
            } catch (\Throwable $e) {
                Log::error('EMP_LOGIN_ERR', ['err' => $e->getMessage()]);
                SendSms::sendMessageWA($phone, "DB laporan error / koneksi lambat bre.\nCoba lagi sebentar ya.");
                return response()->json(['ok' => true]);
            }

            if (!$user) {
                SendSms::sendMessageWA(
                    $phone,
                    "⚠️ Login gagal.\n".
                    "Cek ID / password ya bre.\n".
                    "Contoh: *SPY-0025 123456*\n".
                    "Ketik *ULANG* untuk coba lagi."
                );
                return response()->json(['ok' => true]);
            }

            // ✅ Gender wajib P/L dari db_laporan.dbo.w_user.gender
            $gender = $this->extractGender($user);

            DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                'employee_id' => $employeeId,
                'employee_name' => $user->name ?? null,
                'gender' => $gender,
                'step' => 'ASK_ALL',
                'updated_at' => now(),
            ]);

            $nama = $user->name ?? $employeeId;

            SendSms::sendMessageWA(
                $phone,
                "Halo *{$nama}* 👋\n".
                "Login sukses ✅\n\n".
                "Kirim data *sekalian* pakai spasi:\n".
                "*LP BB TB*\n".
                "Contoh: *80 70 164*\n\n".
                "LP=Lingkar Pinggang(cm)\nBB=Berat(kg)\nTB=Tinggi(cm)"
            );

            return response()->json(['ok' => true]);
        }

        // =========================
        // STEP 2: ASK_ALL (LP BB TB pakai spasi)
        // =========================
        if ($step === 'ASK_ALL') {

            $parts = preg_split('/\s+/', trim($message));
            if (count($parts) !== 3) {
                SendSms::sendMessageWA(
                    $phone,
                    "Format belum sesuai bre.\n".
                    "Kirim dengan spasi:\n".
                    "*LP BB TB*\n".
                    "Contoh: *80 70 164*"
                );
                return response()->json(['ok' => true]);
            }

            $lp = (int) filter_var($parts[0], FILTER_SANITIZE_NUMBER_INT);
            $bb = (float) str_replace(',', '.', (string)$parts[1]);
            $tb = (int) filter_var($parts[2], FILTER_SANITIZE_NUMBER_INT);

            // Validasi
            if ($lp < 40 || $lp > 200) {
                SendSms::sendMessageWA($phone, "LP tidak valid (40-200).\nContoh: *80 70 164*");
                return response()->json(['ok' => true]);
            }
            if (!is_numeric($bb) || $bb < 20 || $bb > 300) {
                SendSms::sendMessageWA($phone, "BB tidak valid (20-300).\nContoh: *80 70 164*");
                return response()->json(['ok' => true]);
            }
            if ($tb < 100 || $tb > 250) {
                SendSms::sendMessageWA($phone, "TB tidak valid (100-250).\nContoh: *80 70 164*");
                return response()->json(['ok' => true]);
            }

            $employeeId   = $session->employee_id ?? null;
            $employeeName = $session->employee_name ?? null;
            $gender       = strtoupper((string)($session->gender ?? ''));

            if (!$employeeId || ($gender !== 'L' && $gender !== 'P')) {
                $this->resetSession($phone);
                SendSms::sendMessageWA($phone, "Session error bre.\nKetik *ULANG* lalu login lagi dari awal ya.");
                return response()->json(['ok' => true]);
            }

            // ✅ BMI Excel (H)
            $bmiH = $this->bmiExcelH($gender, $tb, $lp);

            // ✅ Selisih Excel (pakai H)
            $selisih = $this->hitungSelisihExcel($gender, $bmiH);

            // ✅ Ket sederhana
            $ket = $this->ketExcel($gender, $bmiH);

            // =========================
            // Cek sudah submit bulan ini? (di db_laporan)
            // =========================
            $already = DB::table('dbo.employee_measurements')
                ->where('employee_id', $employeeId)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->exists();

            if ($already) {
                $this->resetSession($phone);

                SendSms::sendMessageWA(
                    $phone,
                    "⚠️ Maaf bre, *{$employeeName}* (ID: *{$employeeId}*) sudah submit *bulan ini*.\n".
                    "Tidak bisa submit lagi.\n\n".
                    "Kalau ada koreksi, hubungi admin ya."
                );

                return response()->json(['ok' => true]);
            }

            // =========================
            // Simpan ke db_laporan
            // =========================
            try {
                DB::table('dbo.employee_measurements')
                    ->insert([
                        'employee_id' => $employeeId,
                        'employee_name' => $employeeName,
                        'phone' => $phone,
                        'waist_cm' => $lp,
                        'weight_kg' => $bb,
                        'height_cm' => $tb,
                        'bmi' => $bmiH,
                        'selisih' => $selisih,
                        'ket' => $ket,
                        'periode'       => date('Y-m-01'),
                        'created_at' => now(),
                    ]);
                    
            } catch (\Throwable $e) {
                Log::error('MEAS_INSERT_ERR', ['err' => $e->getMessage()]);
                SendSms::sendMessageWA($phone, "Gagal simpan data bre (DB error). Coba lagi ya.");
                return response()->json(['ok' => true]);
            }

            // Akumulasi bulan ini (di db_laporan)
            $totalMonth = DB::table('dbo.employee_measurements')
                ->where('employee_id', $employeeId)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            $this->resetSession($phone);

            $nama = $employeeName ?: $employeeId;

            SendSms::sendMessageWA(
                $phone,
                "✅ *Data tersimpan*\n".
                "Nama: *{$nama}*\n".
                "ID: *{$employeeId}*\n".
                "Gender: *{$gender}*\n".
                "LP: *{$lp} cm*\n".
                "BB: *{$bb} kg*\n".
                "TB: *{$tb} cm*\n".
                "BMI(H): *{$bmiH}*\n".
                "Selisih: *{$selisih}*\n".
                "Ket: *{$ket}*\n\n".
                "Total submit bulan ini: *{$totalMonth}*\n".
                "Ketik *ULANG* untuk login lagi."
            );

            return response()->json(['ok' => true]);
        }

        // =========================
        // Fallback
        // =========================
        $this->resetSession($phone);
        SendSms::sendMessageWA(
            $phone,
            "Session ke-reset bre.\n".
            "Login dulu:\n".
            "*EMPLOYEEID PASSWORD*\n".
            "Contoh: *SPY-0025 123456*"
        );
        return response()->json(['ok' => true]);
    }

    // =====================================================
    // Helpers
    // =====================================================

    private function resetSession(string $phone): void
    {
        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'step' => 'ASK_ID',
            'employee_id' => null,
            'employee_name' => null,
            'gender' => null,
            'temp_waist' => null,
            'temp_weight' => null,
            'temp_height' => null,
            'updated_at' => now(),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim((string)$phone);
        $phone = str_replace(['+', ' ', '-', '(', ')'], '', $phone);

        // 0xxx -> 62xxx
        if (preg_match('/^0\d+$/', $phone)) {
            $phone = preg_replace('/^0/', '62', $phone);
        }

        // 8xxx -> 628xxx
        if (preg_match('/^8\d+$/', $phone)) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * True jika token pertama mirip employeeid (SPY-0025),
     * walaupun message ada password setelahnya.
     */
    private function looksLikeEmployeeId(string $text): bool
    {
        $first = strtoupper(trim(explode(' ', trim($text))[0]));
        return (bool) preg_match('/^[A-Z]{2,10}-\d{2,10}$/', $first);
    }

    private function extractGender($user): string
    {
        // Wajib dari kolom gender (P/L)
        $g = strtoupper(trim((string)($user->gender ?? '')));
        if ($g === 'L' || $g === 'P') return $g;

        // fallback aman
        return 'L';
    }

    /**
     * BMI Excel (H):
     * =ROUNDDOWN(IF(P,76-(20*TB/LP),IF(L,64-(20*TB/LP),"FALSE")),0)
     */
    private function bmiExcelH(string $gender, int $tbCm, int $lpCm): int
    {
        $gender = strtoupper(trim($gender));
        if ($lpCm <= 0) return 0;

        if ($gender === 'P') {
            $val = 76 - (20 * $tbCm / $lpCm);
        } else { // default L
            $val = 64 - (20 * $tbCm / $lpCm);
        }

        return (int) floor($val);
    }

    /**
     * Selisih Excel (pakai H):
     * =IF(AND(P,H>31),H-31,IF(AND(P,H<25),25-H,IF(AND(L,H>24),H-24,IF(AND(L,H<18),18-H,0))))
     */
    private function hitungSelisihExcel(string $gender, int $bmiH): int
    {
        $gender = strtoupper(trim($gender));

        if ($gender === 'P') {
            if ($bmiH > 31) return $bmiH - 31;
            if ($bmiH < 25) return 25 - $bmiH;
            return 0;
        }

        // default L
        if ($bmiH > 24) return $bmiH - 24;
        if ($bmiH < 18) return 18 - $bmiH;
        return 0;
    }

    /**
     * Ket simple (normal/gemuk/kurus)
     * - L: <18 kurus, >24 gemuk
     * - P: <25 kurus, >31 gemuk
     */
    private function ketExcel(string $gender, int $bmiH): string
    {
        $gender = strtoupper(trim($gender));

        if ($gender === 'P') {
            if ($bmiH > 31) return 'gemuk';
            if ($bmiH < 25) return 'kurus';
            return 'normal';
        }

        // default L
        if ($bmiH > 24) return 'gemuk';
        if ($bmiH < 18) return 'kurus';
        return 'normal';
    }
}