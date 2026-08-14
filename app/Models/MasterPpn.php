<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPpn extends Model
{
    protected $fillable = [
        'nama_pajak',
        'persentase',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'persentase' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
