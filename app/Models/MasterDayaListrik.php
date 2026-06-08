<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDayaListrik extends Model
{
    protected $fillable = [
        'daya',
        'harga_per_kwh',
        'ppn_persen',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'harga_per_kwh' => 'decimal:2',
        'ppn_persen' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}