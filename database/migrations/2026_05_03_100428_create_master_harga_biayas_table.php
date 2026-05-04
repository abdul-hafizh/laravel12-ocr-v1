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
        Schema::create('master_harga_biayas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('master_cabang_id')
                ->nullable()
                ->constrained('master_cabangs')
                ->nullOnDelete();

            $table->foreignId('master_vendor_id')
                ->nullable()
                ->constrained('master_vendors')
                ->nullOnDelete();

            $table->string('kategori_biaya');
            $table->string('nama_biaya');

            $table->string('tipe_harga')->nullable();
            $table->decimal('nominal', 18, 2)->default(0);

            $table->string('satuan')->nullable();

            $table->boolean('is_coa')->default(false);
            $table->string('kode_coa')->nullable();
            $table->string('nama_coa')->nullable();

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
        Schema::dropIfExists('master_harga_biayas');
    }
};
