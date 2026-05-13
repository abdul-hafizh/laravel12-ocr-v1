<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_cabangs', function (Blueprint $table) {
            $table->unsignedBigInteger('pic_user_id')->nullable()->after('alamat');
        });

        // optional: kalau mau hapus kolom lama
        Schema::table('master_cabangs', function (Blueprint $table) {
            if (Schema::hasColumn('master_cabangs', 'pic')) {
                $table->dropColumn('pic');
            }

            if (Schema::hasColumn('master_cabangs', 'no_hp')) {
                $table->dropColumn('no_hp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_cabangs', function (Blueprint $table) {
            $table->string('pic')->nullable();
            $table->string('no_hp')->nullable();

            if (Schema::hasColumn('master_cabangs', 'pic_user_id')) {
                $table->dropColumn('pic_user_id');
            }
        });
    }
};