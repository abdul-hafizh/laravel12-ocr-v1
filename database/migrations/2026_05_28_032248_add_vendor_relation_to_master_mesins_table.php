<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_mesins', function (Blueprint $table) {

            if (!Schema::hasColumn('master_mesins', 'master_vendor_id')) {
                $table->foreignId('master_vendor_id')
                    ->nullable()
                    ->after('master_cabang_id')
                    ->constrained('master_vendors')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('master_mesins', 'harga_color_a3')) {
                $table->decimal('harga_color_a3', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('master_mesins', 'harga_color_a4')) {
                $table->decimal('harga_color_a4', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('master_mesins', 'harga_bw_a3')) {
                $table->decimal('harga_bw_a3', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('master_mesins', 'harga_bw_a4')) {
                $table->decimal('harga_bw_a4', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('master_mesins', 'free_klik_percent')) {
                $table->decimal('free_klik_percent', 8, 4)->default(0);
            }

            if (!Schema::hasColumn('master_mesins', 'minimum_charge')) {
                $table->decimal('minimum_charge', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('master_mesins', 'minimum_charge_type')) {
                $table->string('minimum_charge_type')->nullable();
            }

            if (!Schema::hasColumn('master_mesins', 'harga_setelah_minimum_charge')) {
                $table->decimal('harga_setelah_minimum_charge', 15, 2)->default(0);
            }

            if (!Schema::hasColumn('master_mesins', 'status_kepemilikan')) {
                $table->string('status_kepemilikan')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_mesins', function (Blueprint $table) {

            if (Schema::hasColumn('master_mesins', 'master_vendor_id')) {
                $table->dropConstrainedForeignId('master_vendor_id');
            }

            $columns = [
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