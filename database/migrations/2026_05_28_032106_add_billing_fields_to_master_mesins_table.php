<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('master_mesins', function (Blueprint $table) {

            if (!Schema::hasColumn('master_mesins', 'vendor')) {
                $table->string('vendor')->nullable()->after('master_cabang_id');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_color_a3')) {
                $table->decimal('harga_color_a3', 15, 2)
                    ->default(0)
                    ->after('harga_long_sheet');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_color_a4')) {
                $table->decimal('harga_color_a4', 15, 2)
                    ->default(0)
                    ->after('harga_color_a3');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_bw_a3')) {
                $table->decimal('harga_bw_a3', 15, 2)
                    ->default(0)
                    ->after('harga_color_a4');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_bw_a4')) {
                $table->decimal('harga_bw_a4', 15, 2)
                    ->default(0)
                    ->after('harga_bw_a3');
            }

            if (!Schema::hasColumn('master_mesins', 'free_klik_percent')) {
                $table->decimal('free_klik_percent', 8, 4)
                    ->default(0)
                    ->after('harga_bw_a4');
            }

            if (!Schema::hasColumn('master_mesins', 'minimum_charge')) {
                $table->decimal('minimum_charge', 15, 2)
                    ->default(0)
                    ->after('free_klik_percent');
            }

            if (!Schema::hasColumn('master_mesins', 'minimum_charge_type')) {
                $table->string('minimum_charge_type')
                    ->nullable()
                    ->after('minimum_charge');
            }

            if (!Schema::hasColumn('master_mesins', 'harga_setelah_minimum_charge')) {
                $table->decimal('harga_setelah_minimum_charge', 15, 2)
                    ->default(0)
                    ->after('minimum_charge_type');
            }

            if (!Schema::hasColumn('master_mesins', 'status_kepemilikan')) {
                $table->string('status_kepemilikan')
                    ->nullable()
                    ->after('harga_setelah_minimum_charge');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_mesins', function (Blueprint $table) {

            $columns = [
                'vendor',
                'harga_color_a3',
                'harga_color_a4',
                'harga_bw_a3',
                'harga_bw_a4',
                'free_klik_percent',
                'minimum_charge',
                'minimum_charge_type',
                'harga_setelah_minimum_charge',
                'status_kepemilikan',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('master_mesins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};