<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleWhatsappMenu extends Model
{
    protected $fillable = [
        'role_id',
        'menu_key',
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