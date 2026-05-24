<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_skpds', function (Blueprint $table) {
            if (!Schema::hasColumn('master_skpds', 'master_cabang_id')) {
                $table->foreignId('master_cabang_id')
                    ->nullable()
                    ->constrained('master_cabangs')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('master_skpds', 'user_ids')) {
                $table->json('user_ids')->nullable();
            }

            if (!Schema::hasColumn('master_skpds', 'jenis')) {
                $table->string('jenis')->nullable();
            }

            if (!Schema::hasColumn('master_skpds', 'foto')) {
                $table->string('foto')->nullable();
            }
        });

        $this->dropDefaultConstraint('master_skpds', 'reminder_hari');

        DB::statement("ALTER TABLE master_skpds ALTER COLUMN reminder_hari NVARCHAR(MAX) NULL");
    }

    public function down(): void
    {
        $this->dropDefaultConstraint('master_skpds', 'reminder_hari');

        DB::statement("ALTER TABLE master_skpds ALTER COLUMN reminder_hari INT NULL");

        Schema::table('master_skpds', function (Blueprint $table) {
            if (Schema::hasColumn('master_skpds', 'master_cabang_id')) {
                $table->dropConstrainedForeignId('master_cabang_id');
            }

            if (Schema::hasColumn('master_skpds', 'user_ids')) {
                $table->dropColumn('user_ids');
            }

            if (Schema::hasColumn('master_skpds', 'jenis')) {
                $table->dropColumn('jenis');
            }

            if (Schema::hasColumn('master_skpds', 'foto')) {
                $table->dropColumn('foto');
            }
        });
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