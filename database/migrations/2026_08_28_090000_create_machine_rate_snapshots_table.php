<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_rate_snapshots', function (Blueprint $table) {
            $table->id();

            $table->string('report_type', 20);
            $table->unsignedBigInteger('master_mesin_id');
            $table->unsignedBigInteger('cabang_id')->default(0);
            $table->string('serial_number', 100)->nullable();

            $table->date('periode_start');
            $table->date('periode_end');

            $table->decimal('harga_color_a3', 15, 2)->nullable();
            $table->decimal('harga_color_a4', 15, 2)->nullable();
            $table->decimal('harga_bw_a3', 15, 2)->nullable();
            $table->decimal('harga_bw_a4', 15, 2)->nullable();

            $table->decimal('minimum_charge_click', 15, 2)->nullable();
            $table->string('minimum_charge_size', 10)->nullable();
            $table->decimal('minimum_charge_nominal', 15, 2)->nullable();

            $table->decimal('over_click_color_a3', 15, 2)->nullable();
            $table->decimal('over_click_color_a4', 15, 2)->nullable();
            $table->decimal('over_click_bw_a3', 15, 2)->nullable();
            $table->decimal('over_click_bw_a4', 15, 2)->nullable();

            $table->decimal('free_klik_percent', 8, 4)->nullable();

            $table->timestamp('locked_at');
            $table->timestamps();

            $table->unique(
                ['report_type', 'master_mesin_id', 'cabang_id', 'periode_start', 'periode_end'],
                'machine_rate_snapshot_unique'
            );

            $table->index(['master_mesin_id']);
            $table->index(['periode_start', 'periode_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_rate_snapshots');
    }
};
