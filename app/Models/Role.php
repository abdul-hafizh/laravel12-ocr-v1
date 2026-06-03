<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function whatsappMenus()
    {
        return $this->hasMany(RoleWhatsappMenu::class, 'role_id');
    }

    public function urlPermissions()
    {
        return $this->hasMany(RoleUrlPermission::class, 'role_id');
    }
}