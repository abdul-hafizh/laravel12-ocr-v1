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
        Schema::create('master_mesins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('master_cabang_id')
                ->constrained('master_cabangs')
                ->cascadeOnDelete();

            $table->string('kode_mesin')->unique();
            $table->string('nama_mesin');
            $table->string('merk')->nullable();
            $table->string('tipe')->nullable();
            $table->string('serial_number')->nullable();

            $table->decimal('harga_minimum', 18, 2)->default(0);
            $table->decimal('harga_normal', 18, 2)->default(0);

            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_mesins');
    }
};
