<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterMesin extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'kode_mesin',
        'nama_mesin',
        'merk',
        'tipe',
        'serial_number',
        'harga_minimum',
        'harga_normal',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'harga_minimum' => 'decimal:2',
        'harga_normal' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }
}