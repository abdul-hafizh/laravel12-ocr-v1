<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    protected $table = 'telegram_users';

    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'telegram_chat_id' => 'integer',
        'telegram_user_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}