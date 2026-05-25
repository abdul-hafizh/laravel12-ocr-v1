<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_mesins', function (Blueprint $table) {
            if (Schema::hasColumn('master_mesins', 'harga_normal')) {
                $table->renameColumn('harga_normal', 'harga_maksimum');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_bw')) {
                $table->decimal('harga_bw', 18, 2)->default(0)->after('harga_maksimum');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_color')) {
                $table->decimal('harga_color', 18, 2)->default(0)->after('harga_bw');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_long_sheet')) {
                $table->decimal('harga_long_sheet', 18, 2)->default(0)->after('harga_color');
            }
        });

        if (Schema::hasColumn('master_mesins', 'kode_mesin')) {
            DB::statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes 
                    WHERE name = 'master_mesins_kode_mesin_unique'
                    AND object_id = OBJECT_ID('master_mesins')
                )
                DROP INDEX master_mesins_kode_mesin_unique ON master_mesins
            ");

            Schema::table('master_mesins', function (Blueprint $table) {
                $table->dropColumn('kode_mesin');
            });
        }
    }

    public function down(): void
    {
        Schema::table('master_mesins', function (Blueprint $table) {
            if (!Schema::hasColumn('master_mesins', 'kode_mesin')) {
                $table->string('kode_mesin')->nullable();
            }

            if (Schema::hasColumn('master_mesins', 'harga_maksimum')) {
                $table->renameColumn('harga_maksimum', 'harga_normal');
            }

            if (Schema::hasColumn('master_mesins', 'harga_bw')) {
                $table->dropColumn('harga_bw');
            }

            if (Schema::hasColumn('master_mesins', 'harga_color')) {
                $table->dropColumn('harga_color');
            }

            if (Schema::hasColumn('master_mesins', 'harga_long_sheet')) {
                $table->dropColumn('harga_long_sheet');
            }
        });
    }
};