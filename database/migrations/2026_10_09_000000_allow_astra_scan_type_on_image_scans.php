<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CHECK constraint image_scans.scan_type (dibuat langsung di DB, bukan lewat
 * migration) belum mengizinkan 'astra', padahal menu Astragraphia sudah ada
 * di bot & SummaryController::astraBilling(). Akibatnya setiap foto Astra
 * gagal INSERT dan tagihan Astragraphia tidak pernah muncul.
 *
 * Nama constraint aslinya auto-generate SQL Server (CK__image_sca__scan___xxxx),
 * jadi dicari dinamis lalu diganti dengan constraint bernama tetap.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'CK_image_scans_scan_type';

    private const BASE_TYPES = ['online_receipt', 'electricity', 'printer', 'part_maintenance', 'cea', 'asaba'];

    public function up(): void
    {
        $this->replaceConstraint([...self::BASE_TYPES, 'astra']);
    }

    public function down(): void
    {
        $this->replaceConstraint(self::BASE_TYPES);
    }

    private function replaceConstraint(array $types): void
    {
        $existing = DB::select("
            SELECT cc.name
            FROM sys.check_constraints cc
            INNER JOIN sys.columns c
                ON c.object_id = cc.parent_object_id
                AND c.column_id = cc.parent_column_id
            WHERE cc.parent_object_id = OBJECT_ID('dbo.image_scans')
            AND c.name = 'scan_type'
        ");

        foreach ($existing as $constraint) {
            DB::statement("ALTER TABLE dbo.image_scans DROP CONSTRAINT [{$constraint->name}]");
        }

        $list = implode(', ', array_map(fn ($t) => "N'{$t}'", $types));

        DB::statement("ALTER TABLE dbo.image_scans ADD CONSTRAINT " . self::CONSTRAINT . " CHECK (scan_type IN ({$list}))");
    }
};
