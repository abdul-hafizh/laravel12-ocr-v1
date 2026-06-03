<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterKendaraan extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'finance_user_id',
        'finance_user_ids',

        'jenis_kendaraan',
        'nomor_polisi',
        'merk',
        'tipe',
        'tahun_pembelian',
        'nama_pemilik',

        'tanggal_jatuh_tempo',
        'reminder_hari',

        'tanggal_ganti_kaleng',
        'reminder_ganti_kaleng_hari',

        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_ganti_kaleng' => 'date',
        'is_active' => 'boolean',
        'reminder_hari' => 'array',
        'finance_user_ids' => 'array',
        'reminder_ganti_kaleng_hari' => 'array',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }

    public function financeUser()
    {
        return $this->belongsTo(User::class, 'finance_user_id');
    }
}