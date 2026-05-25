<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterMesinPart extends Model
{
    protected $fillable = [
        'master_mesin_id',
        'nama_part',
        'harga_part',
    ];

    protected $casts = [
        'harga_part' => 'decimal:2',
    ];

    public function mesin()
    {
        return $this->belongsTo(MasterMesin::class, 'master_mesin_id');
    }
}