-- =====================================================================
-- Update harga click charge BARU mesin Samafitro (master_vendor_id = 2)
-- Sumber: tabel "Biaya Click Charge Baru".
--   * IPR C650 & C710 : tarif bertingkat (< 30.000 / > 30.000 klik A4),
--                        belum termasuk PPN 11%.
--   * IPR V700        : tarif flat, sudah termasuk PPN 11%.
-- Harga A3 = 2 x harga A4 (sesuai rumus Excel billing Samafitro).
-- Kolom lain (minimum_charge_*, free_klik_percent, dll) TIDAK diubah.
-- =====================================================================

-- 1. Cek dulu mesin yang akan kena update
SELECT id, nama_mesin, tipe, serial_number, is_active,
       harga_color_a3, harga_color_a4, harga_bw_a3, harga_bw_a4,
       over_click_color_a3, over_click_color_a4, over_click_bw_a3, over_click_bw_a4,
       minimum_charge_click
FROM dbo.master_mesins
WHERE master_vendor_id = 2
  AND (nama_mesin LIKE '%C650%' OR nama_mesin LIKE '%C710%' OR nama_mesin LIKE '%V700%')
ORDER BY nama_mesin, serial_number;

BEGIN TRANSACTION;

-- 2. IPR C650 & C710: < 30.000 = 605 / 302, > 30.000 = 550 / 275
UPDATE dbo.master_mesins
SET harga_color_a4      = 605,
    harga_color_a3      = 1210,   -- 2 x 605
    harga_bw_a4         = 302,
    harga_bw_a3         = 604,    -- 2 x 302
    over_click_color_a4 = 550,
    over_click_color_a3 = 1100,   -- 2 x 550
    over_click_bw_a4    = 275,
    over_click_bw_a3    = 550,    -- 2 x 275
    updated_at          = GETDATE()
WHERE master_vendor_id = 2
  AND (nama_mesin LIKE '%C650%' OR nama_mesin LIKE '%C710%');

-- 3. IPR V700: flat 550 / 275, tanpa tarif bertingkat
UPDATE dbo.master_mesins
SET harga_color_a4      = 550,
    harga_color_a3      = 1100,   -- 2 x 550
    harga_bw_a4         = 275,
    harga_bw_a3         = 550,    -- 2 x 275
    over_click_color_a4 = 0,
    over_click_color_a3 = 0,
    over_click_bw_a4    = 0,
    over_click_bw_a3    = 0,
    updated_at          = GETDATE()
WHERE master_vendor_id = 2
  AND nama_mesin LIKE '%V700%';

-- 4. Cek hasilnya, lalu COMMIT kalau sudah benar (ROLLBACK kalau tidak)
SELECT id, nama_mesin, serial_number,
       harga_color_a3, harga_color_a4, harga_bw_a3, harga_bw_a4,
       over_click_color_a3, over_click_color_a4, over_click_bw_a3, over_click_bw_a4
FROM dbo.master_mesins
WHERE master_vendor_id = 2
ORDER BY nama_mesin, serial_number;

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
