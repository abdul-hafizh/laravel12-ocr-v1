<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_maintenance_costs', function (Blueprint $table) {
            $table->id();

            $table->date('periode_start')->nullable();
            $table->date('periode_end')->nullable();

            $table->unsignedBigInteger('image_scan_id')->nullable();
            $table->unsignedBigInteger('cabang_id')->nullable();
            $table->unsignedBigInteger('master_mesin_id')->nullable();

            $table->string('serial_number')->nullable();

            $table->string('cost_type', 20);

            $table->decimal('nominal', 18, 2)->default(0);

            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->index(['periode_start', 'periode_end']);
            $table->index(['cabang_id']);
            $table->index(['master_mesin_id']);
            $table->index(['cost_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_maintenance_costs');
    }
};