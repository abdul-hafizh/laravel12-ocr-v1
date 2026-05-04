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
        Schema::create('master_skpds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('master_kendaraan_id')
                ->nullable()
                ->constrained('master_kendaraans')
                ->nullOnDelete();

            $table->string('nomor_skpd')->unique();
            $table->string('nama_pemilik')->nullable();
            $table->string('nomor_polisi')->nullable();

            $table->decimal('nominal_pajak', 18, 2)->default(0);
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
        Schema::dropIfExists('master_skpds');
    }
};
