<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_maintenance_costs', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('nominal');
        });
    }

    public function down(): void
    {
        Schema::table('machine_maintenance_costs', function (Blueprint $table) {
            $table->dropColumn('locked_at');
        });
    }
};
