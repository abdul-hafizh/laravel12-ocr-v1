<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterMesin extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'nama_mesin',
        'merk',
        'tipe',
        'serial_number',
        'harga_minimum',
        'harga_maksimum',
        'harga_bw',
        'harga_color',
        'harga_long_sheet',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'harga_minimum' => 'decimal:2',
        'harga_maksimum' => 'decimal:2',
        'harga_bw' => 'decimal:2',
        'harga_color' => 'decimal:2',
        'harga_long_sheet' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }

    public function maintenanceParts()
    {
        return $this->hasMany(MasterMesinPart::class);
    }
}