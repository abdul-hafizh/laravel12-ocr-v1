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
        Schema::table('master_kendaraans', function (Blueprint $table) {
            $table->foreignId('finance_user_id')
                ->nullable()
                ->after('master_cabang_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('master_kendaraans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finance_user_id');
        });
    }
};
