<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterTokenListrik extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'nomor_meter',
        'nama_pelanggan',
        'daya',
        'nominal_default',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'nominal_default' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }
}