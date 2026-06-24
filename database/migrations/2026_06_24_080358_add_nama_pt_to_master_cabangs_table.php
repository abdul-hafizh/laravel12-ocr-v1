<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('master_cabangs', function (Blueprint $table) {
            $table->string('nama_pt')->after('nama_cabang')->nullable();
        });
    }

    public function down()
    {
        Schema::table('master_cabangs', function (Blueprint $table) {
            $table->dropColumn('nama_pt');
        });
    }
};
