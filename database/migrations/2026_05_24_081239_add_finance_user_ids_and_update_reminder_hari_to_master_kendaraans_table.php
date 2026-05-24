<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('master_kendaraans', 'finance_user_ids')) {
            Schema::table('master_kendaraans', function (Blueprint $table) {
                $table->json('finance_user_ids')->nullable()->after('finance_user_id');
            });
        }

        $this->dropDefaultConstraint('master_kendaraans', 'reminder_hari');

        DB::statement("ALTER TABLE master_kendaraans ALTER COLUMN reminder_hari NVARCHAR(MAX) NULL");
    }

    public function down(): void
    {
        $this->dropDefaultConstraint('master_kendaraans', 'reminder_hari');

        DB::statement("ALTER TABLE master_kendaraans ALTER COLUMN reminder_hari INT NULL");

        if (Schema::hasColumn('master_kendaraans', 'finance_user_ids')) {
            Schema::table('master_kendaraans', function (Blueprint $table) {
                $table->dropColumn('finance_user_ids');
            });
        }
    }

    private function dropDefaultConstraint(string $table, string $column): void
    {
        $constraint = DB::selectOne("
            SELECT dc.name AS constraint_name
            FROM sys.default_constraints dc
            INNER JOIN sys.columns c 
                ON dc.parent_object_id = c.object_id 
                AND dc.parent_column_id = c.column_id
            INNER JOIN sys.tables t 
                ON t.object_id = dc.parent_object_id
            WHERE t.name = ? 
              AND c.name = ?
        ", [$table, $column]);

        if ($constraint && $constraint->constraint_name) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint->constraint_name}");
        }
    }
};