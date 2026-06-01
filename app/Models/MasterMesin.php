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
        'minimum_charge_size',

        'harga_color_a3',
        'harga_color_a4',
        'harga_bw_a3',
        'harga_bw_a4',

        'minimum_charge_click',
        'minimum_charge_nominal',

        'over_click_color_a3',
        'over_click_color_a4',
        'over_click_bw_a3',
        'over_click_bw_a4',

        'free_klik_percent',
        'status_kepemilikan',

        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'harga_color_a3' => 'decimal:2',
        'harga_color_a4' => 'decimal:2',
        'harga_bw_a3' => 'decimal:2',
        'harga_bw_a4' => 'decimal:2',

        'minimum_charge_click' => 'decimal:2',
        'minimum_charge_nominal' => 'decimal:2',

        'over_click_color_a3' => 'decimal:2',
        'over_click_color_a4' => 'decimal:2',
        'over_click_bw_a3' => 'decimal:2',
        'over_click_bw_a4' => 'decimal:2',

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