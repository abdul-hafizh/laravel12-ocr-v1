<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CeaBillingCost extends Model
{
    protected $fillable = [
        'periode_start',
        'periode_end',
        'image_scan_id',
        'cabang_id',
        'master_mesin_id',
        'serial_number',
        'contract_service',
        'biaya_tinta',
    ];

    protected $casts = [
        'periode_start' => 'date',
        'periode_end' => 'date',
        'contract_service' => 'decimal:2',
        'biaya_tinta' => 'decimal:2',
    ];
}