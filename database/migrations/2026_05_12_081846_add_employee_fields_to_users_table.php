<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id', 40)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('gender', 1)->nullable(); // L / P
            $table->string('role', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_delete')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employee_id',
                'phone',
                'gender',
                'role',
                'is_active',
                'is_delete',
            ]);
        });
    }
};