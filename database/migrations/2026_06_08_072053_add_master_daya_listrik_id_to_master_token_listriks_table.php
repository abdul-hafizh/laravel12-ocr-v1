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
        Schema::table('master_token_listriks', function (Blueprint $table) {
            $table->foreignId('master_daya_listrik_id')
                ->nullable()
                ->after('nama_pelanggan')
                ->constrained('master_daya_listriks')
                ->nullOnDelete();

            $table->dropColumn(['daya', 'nominal_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_token_listriks', function (Blueprint $table) {
            //
        });
    }
};
