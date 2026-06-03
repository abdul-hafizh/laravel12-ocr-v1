<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_kendaraans', function (Blueprint $table) {

            $table->date('tanggal_ganti_kaleng')
                ->nullable()
                ->after('tanggal_jatuh_tempo');

            $table->json('reminder_ganti_kaleng_hari')
                ->nullable()
                ->after('reminder_hari');
        });
    }

    public function down(): void
    {
        Schema::table('master_kendaraans', function (Blueprint $table) {

            $table->dropColumn([
                'tanggal_ganti_kaleng',
                'reminder_ganti_kaleng_hari',
            ]);
        });
    }
};