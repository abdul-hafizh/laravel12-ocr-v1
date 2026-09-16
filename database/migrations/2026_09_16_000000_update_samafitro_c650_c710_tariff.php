<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan tarif Click Charge mesin Samafitro tipe C650/C710 sesuai surat
 * "Penyesuaian Tarif Baru PT. Samafitro" (No. 029/SK-PPS/VI/2026, efektif
 * 1 Juli 2026): BW A4 Rp 302 (<30.000 klik) / Rp 275 (>30.001 klik), Color A4
 * Rp 605 (<30.000 klik) / Rp 605 (>30.001 klik) -- Rp 550, belum termasuk PPN 11%.
 *
 * Nilai yang disimpan di sini SUDAH termasuk PPN 11% (302->335, 605->672,
 * 275->305, 550->611), supaya konsisten dengan mesin Canon V700 vendor yang
 * sama (tarifnya sudah termasuk PPN di master data) -- sistem ini PPN-nya
 * per-cabang, bukan per-mesin, dan beberapa cabang punya mesin V700 + C650/C710
 * sekaligus, sehingga baku-in PPN ke tarif per-mesin lebih aman daripada
 * mengandalkan PPN per-cabang (yang akan salah dobel kena PPN utk V700-nya).
 *
 * minimum_charge_size diisi 'A4' (beberapa mesin sebelumnya kosong) supaya
 * tarif over_click (tier >30.001 klik) benar-benar aktif -- lihat perbaikan
 * terkait di app/Http/Controllers/SummaryController.php::printerBilling().
 */
return new class extends Migration
{
    private array $serials = [
        'XMS03193',
        'WJD00901',
        'XMS01448',
        '2NT02555',
        '2NTO1531',
        '2NT01545',
        '3EW03943',
    ];

    /**
     * Nilai sebelum perubahan, per serial, untuk rollback (down()).
     */
    private array $previousValues = [
        'XMS03193' => ['harga_bw_a4' => 335.00, 'harga_color_a4' => 672.00, 'over_click_bw_a4' => 0.00, 'over_click_color_a4' => 0.00, 'minimum_charge_size' => ''],
        'WJD00901' => ['harga_bw_a4' => 335.00, 'harga_color_a4' => 672.00, 'over_click_bw_a4' => 0.00, 'over_click_color_a4' => 0.00, 'minimum_charge_size' => ''],
        'XMS01448' => ['harga_bw_a4' => 304.00, 'harga_color_a4' => 611.00, 'over_click_bw_a4' => 278.00, 'over_click_color_a4' => 555.00, 'minimum_charge_size' => 'A4'],
        '2NT02555' => ['harga_bw_a4' => 335.00, 'harga_color_a4' => 672.00, 'over_click_bw_a4' => 278.00, 'over_click_color_a4' => 555.00, 'minimum_charge_size' => 'A4'],
        '2NTO1531' => ['harga_bw_a4' => 335.00, 'harga_color_a4' => 672.00, 'over_click_bw_a4' => 0.00, 'over_click_color_a4' => 0.00, 'minimum_charge_size' => ''],
        '2NT01545' => ['harga_bw_a4' => 305.00, 'harga_color_a4' => 611.00, 'over_click_bw_a4' => 0.00, 'over_click_color_a4' => 0.00, 'minimum_charge_size' => ''],
        '3EW03943' => ['harga_bw_a4' => 335.00, 'harga_color_a4' => 672.00, 'over_click_bw_a4' => 0.00, 'over_click_color_a4' => 0.00, 'minimum_charge_size' => 'A4'],
    ];

    public function up(): void
    {
        DB::table('dbo.master_mesins')
            ->whereIn('serial_number', $this->serials)
            ->update([
                'harga_bw_a4' => 335.00,
                'harga_color_a4' => 672.00,
                'over_click_bw_a4' => 305.00,
                'over_click_color_a4' => 611.00,
                'minimum_charge_click' => 30000.00,
                'minimum_charge_size' => 'A4',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        foreach ($this->previousValues as $serial => $values) {
            DB::table('dbo.master_mesins')
                ->where('serial_number', $serial)
                ->update(array_merge($values, [
                    'minimum_charge_click' => 30000.00,
                    'updated_at' => now(),
                ]));
        }
    }
};
