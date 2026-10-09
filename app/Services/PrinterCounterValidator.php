<?php

namespace App\Services;

use App\Models\MasterMesin;
use Illuminate\Support\Facades\DB;

/**
 * Pengaman hasil OCR counter printer (Samafitro / Canon, Asaba / Konica
 * Minolta lewat method asaba*, Astragraphia / Fujifilm lewat method astra*)
 * SEBELUM masuk DB maupun saat hitung billing.
 *
 * Dua aturan Samafitro yang terbukti dari data lapangan:
 * 1. "101 Total 1" == bw_a3 + bw_a4 + color_a3 + color_a4 (long sheet sudah
 *    termasuk di counter Large). Semua scan yang benar memenuhi ini, dan
 *    SEMUA scan salah baca yang pernah lolos melanggarnya.
 * 2. Counter tidak pernah turun dibanding foto sebelumnya untuk mesin yang
 *    sama.
 */
class PrinterCounterValidator
{
    public const COUNTER_FIELDS = ['bw_a3', 'bw_a4', 'color_a3', 'color_a4', 'bw_long_sheet', 'color_long_sheet'];

    public const ASABA_COUNTER_FIELDS = ['total_counter', 'printer_counter', 'copy_counter', 'full_color_counter', 'single_color_counter', 'black_counter'];

    public const ASTRA_COUNTER_FIELDS = ['total_impressions', 'color_impressions', 'black_impressions', 'color_large_impressions'];

    public const RETAKE_HINT = 'Mohon foto ulang layar Check Counter: lurus, dekat, tidak silau, dan semua baris + angka di kanan terlihat utuh.';

    /**
     * Cek lengkap untuk foto BARU dari bot (Telegram/WA), sebelum disimpan:
     * rumus layar + counter tidak boleh turun dari foto terakhir mesin itu.
     * Return null kalau lolos / scan type tidak dicek.
     */
    public static function botIngestError(string $scanType, array $data): ?string
    {
        if (!in_array($scanType, ['printer', 'asaba', 'astra'], true)) {
            return null;
        }

        if ($checksumError = self::checksumErrorFor($scanType, $data)) {
            return $checksumError;
        }

        $serial = trim((string) ($data['serial_number'] ?? ''));
        $mesinId = $serial !== ''
            ? MasterMesin::where('serial_number', $serial)->where('is_active', true)->value('id')
            : null;

        return match ($scanType) {
            'printer' => self::regressionError($data, $mesinId),
            'asaba' => self::asabaRegressionError($data, $mesinId),
            'astra' => self::astraRegressionError($data, $mesinId),
        };
    }

    /**
     * Cek rumus layar saja (tanpa cek counter turun) sesuai scan type.
     */
    public static function checksumErrorFor(string $scanType, array $data): ?string
    {
        return match ($scanType) {
            'printer' => self::checksumError($data),
            'asaba' => self::asabaChecksumError($data),
            'astra' => self::astraChecksumError($data),
            default => null,
        };
    }

    public static function counter($value): int
    {
        return (int) preg_replace('/[^0-9]/', '', (string) ($value ?? 0));
    }

    public static function checksumError(array $data): ?string
    {
        $total = self::counter($data['total_counter_mesin'] ?? 0);

        // Sebagian layar tidak menampilkan Total 1 -- tidak bisa dicek di sini,
        // tetap dijaga oleh regressionError().
        if ($total <= 0) {
            return null;
        }

        $sum = self::counter($data['bw_a3'] ?? 0)
            + self::counter($data['bw_a4'] ?? 0)
            + self::counter($data['color_a3'] ?? 0)
            + self::counter($data['color_a4'] ?? 0);

        if ($sum === $total) {
            return null;
        }

        return 'Angka counter tidak konsisten (Total 1 = ' . number_format($total, 0, ',', '.')
            . ', jumlah BW + Color = ' . number_format($sum, 0, ',', '.') . '). ' . self::RETAKE_HINT;
    }

    /**
     * Layar counter Develop / Asaba (Konica Minolta ineo) punya 2 rumus
     * internal yang selalu berlaku di data lapangan:
     *   Total Counter = Printer Total Counter + Copy Total Counter
     *   Total Counter = Full Color + Single Color + Black   (mesin warna)
     */
    public static function asabaChecksumError(array $data): ?string
    {
        $total = self::counter($data['total_counter'] ?? 0);

        if ($total <= 0) {
            return null;
        }

        $printerCopy = self::counter($data['printer_counter'] ?? 0) + self::counter($data['copy_counter'] ?? 0);

        if ($printerCopy > 0 && $printerCopy !== $total) {
            return 'Angka counter tidak konsisten (Total Counter = ' . number_format($total, 0, ',', '.')
                . ', Printer + Copy = ' . number_format($printerCopy, 0, ',', '.') . '). ' . self::RETAKE_HINT;
        }

        $colorBlack = self::counter($data['full_color_counter'] ?? 0)
            + self::counter($data['single_color_counter'] ?? 0)
            + self::counter($data['black_counter'] ?? 0);

        if ($colorBlack > 0 && $colorBlack !== $total) {
            return 'Angka counter tidak konsisten (Total Counter = ' . number_format($total, 0, ',', '.')
                . ', Full Color + Single Color + Black = ' . number_format($colorBlack, 0, ',', '.') . '). ' . self::RETAKE_HINT;
        }

        return null;
    }

    /**
     * Layar "Billing Information" Astragraphia (Fujifilm Apeos):
     *   Total Impressions = Color Impressions + Black Impressions
     *   Color Large Impressions <= Color Impressions (bagian dari color)
     */
    public static function astraChecksumError(array $data): ?string
    {
        $total = self::counter($data['total_impressions'] ?? 0);
        $color = self::counter($data['color_impressions'] ?? 0);
        $black = self::counter($data['black_impressions'] ?? 0);
        $colorLarge = self::counter($data['color_large_impressions'] ?? 0);

        if ($total > 0 && ($color + $black) > 0 && $color + $black !== $total) {
            return 'Angka counter tidak konsisten (Total Impressions = ' . number_format($total, 0, ',', '.')
                . ', Color + Black = ' . number_format($color + $black, 0, ',', '.') . '). ' . self::RETAKE_HINT;
        }

        if ($colorLarge > $color && $color > 0) {
            return 'Angka counter tidak konsisten (Color Large Impressions ' . number_format($colorLarge, 0, ',', '.')
                . ' lebih besar dari Color Impressions ' . number_format($color, 0, ',', '.') . '). ' . self::RETAKE_HINT;
        }

        return null;
    }

    public static function astraRegressionError(array $data, ?int $masterMesinId): ?string
    {
        return self::regressionAgainst('dbo.v_image_scan_astra', self::ASTRA_COUNTER_FIELDS, $data, $masterMesinId);
    }

    public static function asabaRegressionError(array $data, ?int $masterMesinId): ?string
    {
        return self::regressionAgainst('dbo.v_image_scan_asaba', self::ASABA_COUNTER_FIELDS, $data, $masterMesinId);
    }

    public static function regressionError(array $data, ?int $masterMesinId): ?string
    {
        return self::regressionAgainst('dbo.v_image_scan_printers', self::COUNTER_FIELDS, $data, $masterMesinId);
    }

    private static function regressionAgainst(string $view, array $fields, array $data, ?int $masterMesinId): ?string
    {
        if (!$masterMesinId) {
            return null;
        }

        $previous = DB::table($view)
            ->where('master_mesin_id', $masterMesinId)
            ->where('status', '!=', 'failed')
            ->orderByDesc('created_at')
            ->first();

        if (!$previous) {
            return null;
        }

        $turun = [];

        foreach ($fields as $field) {
            $before = self::counter($previous->{$field} ?? 0);
            $after = self::counter($data[$field] ?? 0);

            if ($after < $before) {
                $turun[] = "{$field} " . number_format($after, 0, ',', '.') . ' < ' . number_format($before, 0, ',', '.');
            }
        }

        if (!$turun) {
            return null;
        }

        return 'Counter terbaca lebih kecil dari foto sebelumnya (' . implode(', ', $turun) . '). ' . self::RETAKE_HINT;
    }
}
