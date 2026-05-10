<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Libraries\SendSms;
use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappRouterService;

class WablasWebhookController extends Controller
{
    public function handle(Request $request)
    {
        app(WhatsappRouterService::class)->handle($request);

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