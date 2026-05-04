<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterKendaraan extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'jenis_kendaraan',
        'nomor_polisi',
        'merk',
        'tipe',
        'tahun_pembelian',
        'nama_pemilik',
        'tanggal_jatuh_tempo',
        'reminder_hari',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'tanggal_jatuh_tempo' => 'date',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }
}