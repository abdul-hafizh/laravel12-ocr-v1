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
        Schema::create('master_kendaraans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('master_cabang_id')
                ->nullable()
                ->constrained('master_cabangs')
                ->nullOnDelete();

            $table->string('jenis_kendaraan'); 
            $table->string('nomor_polisi')->unique();
            $table->string('merk')->nullable();
            $table->string('tipe')->nullable();
            $table->integer('tahun_pembelian')->nullable();
            $table->string('nama_pemilik')->nullable();

            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->integer('reminder_hari')->default(14);

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
        Schema::dropIfExists('master_kendaraans');
    }
};
