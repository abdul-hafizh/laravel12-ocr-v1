<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_mesin_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_mesin_id')
                ->constrained('master_mesins')
                ->cascadeOnDelete();
            $table->string('nama_part');
            $table->decimal('harga_part', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_mesin_parts');
    }
};