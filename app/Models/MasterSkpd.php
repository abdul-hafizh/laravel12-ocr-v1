<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterSkpd extends Model
{
    protected $fillable = [
        'master_kendaraan_id',
        'nomor_skpd',
        'nama_pemilik',
        'nomor_polisi',
        'nominal_pajak',
        'tanggal_jatuh_tempo',
        'reminder_hari',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'nominal_pajak' => 'decimal:2',
        'tanggal_jatuh_tempo' => 'date',
        'is_active' => 'boolean',
    ];

    public function kendaraan()
    {
        return $this->belongsTo(MasterKendaraan::class, 'master_kendaraan_id');
    }
}