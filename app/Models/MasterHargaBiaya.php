<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterHargaBiaya extends Model
{
    protected $fillable = [
        'master_cabang_id',
        'master_vendor_id',
        'kategori_biaya',
        'nama_biaya',
        'tipe_harga',
        'nominal',
        'satuan',
        'is_coa',
        'kode_coa',
        'nama_coa',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'is_coa' => 'boolean',
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
}