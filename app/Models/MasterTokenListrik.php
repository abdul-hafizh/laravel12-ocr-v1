<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterTokenListrik extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'master_daya_listrik_id',
        'nomor_meter',
        'nama_pelanggan',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function dayaListrik()
    {
        return $this->belongsTo(MasterDayaListrik::class, 'master_daya_listrik_id');
    }

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }
}