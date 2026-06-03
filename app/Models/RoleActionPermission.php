<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleActionPermission extends Model
{
    protected $fillable = [
        'role_id',
        'action_key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}