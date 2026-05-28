<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterMesin extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'master_vendor_id',

        'nama_mesin',
        'merk',
        'tipe',
        'serial_number',

        'harga_minimum',
        'harga_maksimum',

        'harga_bw',
        'harga_color',
        'harga_long_sheet',

        'harga_color_a3',
        'harga_color_a4',
        'harga_bw_a3',
        'harga_bw_a4',

        'free_klik_percent',
        'minimum_charge',
        'minimum_charge_type',
        'harga_setelah_minimum_charge',
        'status_kepemilikan',

        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'harga_minimum' => 'decimal:2',
        'harga_maksimum' => 'decimal:2',

        'harga_bw' => 'decimal:2',
        'harga_color' => 'decimal:2',
        'harga_long_sheet' => 'decimal:2',

        'harga_color_a3' => 'decimal:2',
        'harga_color_a4' => 'decimal:2',
        'harga_bw_a3' => 'decimal:2',
        'harga_bw_a4' => 'decimal:2',

        'minimum_charge' => 'decimal:2',
        'harga_setelah_minimum_charge' => 'decimal:2',

        'free_klik_percent' => 'decimal:4',

        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'master_cabang_id');
    }

    public function vendor()
    {
        return $this->belongsTo(MasterVendor::class, 'master_vendor_id');
    }

    public function maintenanceParts()
    {
        return $this->hasMany(MasterMesinPart::class);
    }
}