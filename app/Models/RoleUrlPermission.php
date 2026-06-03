<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleUrlPermission extends Model
{
    protected $fillable = [
        'role_id',
        'url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}