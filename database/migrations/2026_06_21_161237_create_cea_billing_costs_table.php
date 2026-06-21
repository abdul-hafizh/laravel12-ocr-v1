<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cea_billing_costs', function (Blueprint $table) {
            $table->id();

            $table->date('periode_start');
            $table->date('periode_end');

            $table->unsignedBigInteger('image_scan_id')->nullable();
            $table->unsignedBigInteger('cabang_id')->nullable();
            $table->unsignedBigInteger('master_mesin_id')->nullable();

            $table->string('serial_number', 100)->nullable();

            $table->decimal('contract_service', 18, 2)->default(300000);
            $table->decimal('biaya_tinta', 18, 2)->default(0);

            $table->timestamps();

            $table->unique([
                'periode_start',
                'periode_end',
                'cabang_id',
                'serial_number',
            ], 'cea_billing_cost_unique');

            $table->index('periode_start');
            $table->index('periode_end');
            $table->index('cabang_id');
            $table->index('master_mesin_id');
            $table->index('serial_number');
            $table->index('image_scan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cea_billing_costs');
    }
};