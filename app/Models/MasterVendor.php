<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterVendor extends Model
{
    protected $fillable = [
        'kode_vendor',
        'nama_vendor',
        'pic',
        'no_hp',
        'email',
        'alamat',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}