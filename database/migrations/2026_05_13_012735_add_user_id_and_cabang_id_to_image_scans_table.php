<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_scans', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->unsignedBigInteger('cabang_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('image_scans', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'cabang_id']);
        });
    }
};