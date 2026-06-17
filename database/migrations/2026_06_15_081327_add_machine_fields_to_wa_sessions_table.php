<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('master_mesin_id')->nullable()->after('scan_type');
            $table->unsignedBigInteger('master_mesin_part_id')->nullable()->after('master_mesin_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'master_mesin_id',
                'master_mesin_part_id',
            ]);
        });
    }
};