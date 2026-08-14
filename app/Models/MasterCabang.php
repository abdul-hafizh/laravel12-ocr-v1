<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterCabang extends Model
{
    protected $fillable = [
        'kode_cabang',
        'nama_cabang',
        'nama_pt',
        'alamat',
        'pic_user_id',
        'ppn_id',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'master_cabang_user')
            ->withTimestamps();
    }

    public function ppn()
    {
        return $this->belongsTo(MasterPpn::class, 'ppn_id');
    }
}