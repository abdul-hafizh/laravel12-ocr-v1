<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCabang extends Model
{
    protected $fillable = [
        'kode_cabang',
        'nama_cabang',
        'alamat',
        'pic_user_id',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function picUser()
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }
}