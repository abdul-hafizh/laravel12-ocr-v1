-- =====================================================================
-- Master Harga Tinta (khusus mesin CEA - milik / sewa)
-- Jalankan manual di SQL Server (bukan via php artisan migrate).
-- =====================================================================

-- 1. Tabel master harga tinta
IF OBJECT_ID('dbo.master_harga_tintas', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.master_harga_tintas (
        id                  BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        nama_tinta          NVARCHAR(150)  NOT NULL,
        -- 'milik' / 'sewa'. NULL = berlaku untuk semua mesin CEA.
        status_kepemilikan  NVARCHAR(20)   NULL,
        harga               DECIMAL(18,2)  NOT NULL CONSTRAINT df_master_harga_tintas_harga DEFAULT (0),
        keterangan          NVARCHAR(MAX)  NULL,
        is_active           BIT            NOT NULL CONSTRAINT df_master_harga_tintas_is_active DEFAULT (1),
        created_at          DATETIME       NULL,
        updated_at          DATETIME       NULL
    );

    CREATE INDEX ix_master_harga_tintas_lookup
        ON dbo.master_harga_tintas (is_active, status_kepemilikan);
END
GO

-- 2. Tambahan kolom di cea_billing_costs untuk menyimpan qty & harga satuan
--    yang dipakai saat lapor. biaya_tinta tetap diisi (qty x harga) seperti sekarang.
IF COL_LENGTH('dbo.cea_billing_costs', 'qty_tinta') IS NULL
    ALTER TABLE dbo.cea_billing_costs ADD qty_tinta INT NULL;
GO

IF COL_LENGTH('dbo.cea_billing_costs', 'harga_tinta_satuan') IS NULL
    ALTER TABLE dbo.cea_billing_costs ADD harga_tinta_satuan DECIMAL(18,2) NULL;
GO

IF COL_LENGTH('dbo.cea_billing_costs', 'master_harga_tinta_id') IS NULL
    ALTER TABLE dbo.cea_billing_costs ADD master_harga_tinta_id BIGINT NULL;
GO

-- 3. Daftarkan URL menu ke role yang sudah punya akses Master PPN
--    (opsional; atau centang manual lewat menu Master Role).
INSERT INTO dbo.role_url_permissions (role_id, url, is_active, created_at, updated_at)
SELECT p.role_id, '/master-harga-tinta', 1, GETDATE(), GETDATE()
FROM dbo.role_url_permissions p
WHERE p.url = '/master-ppn'
  AND p.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM dbo.role_url_permissions x
      WHERE x.role_id = p.role_id AND x.url = '/master-harga-tinta'
  );
GO
