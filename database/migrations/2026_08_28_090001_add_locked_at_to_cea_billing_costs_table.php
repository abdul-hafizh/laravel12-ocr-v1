<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cea_billing_costs', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('biaya_tinta');
        });
    }

    public function down(): void
    {
        Schema::table('cea_billing_costs', function (Blueprint $table) {
            $table->dropColumn('locked_at');
        });
    }
};
