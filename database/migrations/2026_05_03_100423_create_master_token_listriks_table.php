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
        Schema::create('master_token_listriks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('master_cabang_id')
                ->constrained('master_cabangs')
                ->cascadeOnDelete();

            $table->string('nomor_meter')->unique();
            $table->string('nama_pelanggan')->nullable();
            $table->string('daya')->nullable();

            $table->decimal('nominal_default', 18, 2)->default(0);

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
        Schema::dropIfExists('master_token_listriks');
    }
};
