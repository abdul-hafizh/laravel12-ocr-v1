<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterMesinPart extends Model
{
    protected $fillable = [
        'master_mesin_id',
        'nama_part',
    ];

    public function mesin()
    {
        return $this->belongsTo(MasterMesin::class, 'master_mesin_id');
    }
}