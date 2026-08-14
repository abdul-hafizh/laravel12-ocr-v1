<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_cabangs', function (Blueprint $table) {
            $table->foreignId('ppn_id')
                ->nullable()
                ->after('nama_pt')
                ->constrained('master_ppns')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('master_cabangs', function (Blueprint $table) {
            $table->dropForeign(['ppn_id']);
            $table->dropColumn('ppn_id');
        });
    }
};
