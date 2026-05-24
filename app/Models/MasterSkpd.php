<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterSkpd extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'user_ids',
        'jenis',
        'keterangan',
        'tanggal_jatuh_tempo',
        'reminder_hari',
        'nomor_skpd',
        'nominal_pajak',
        'foto',
        'is_active',
    ];

    protected $casts = [
        'user_ids' => 'array',
        'reminder_hari' => 'array',
        'nominal_pajak' => 'decimal:2',
        'tanggal_jatuh_tempo' => 'date',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }
}