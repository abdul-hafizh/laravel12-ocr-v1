<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCabang extends Model
{
    protected $fillable = [
        'kode_cabang',
        'nama_cabang',
        'alamat',
        'pic',
        'no_hp',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}