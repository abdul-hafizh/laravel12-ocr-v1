<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_daya_listriks', function (Blueprint $table) {
            $table->decimal('ppn_persen', 5, 2)
                ->default(11.00)
                ->after('harga_per_kwh');
        });
    }

    public function down(): void
    {
        Schema::table('master_daya_listriks', function (Blueprint $table) {
            $table->dropColumn('ppn_persen');
        });
    }
};