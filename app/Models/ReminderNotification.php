<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderNotification extends Model
{
    protected $fillable = [
        'module',
        'reference_id',
        'user_id',
        'due_date',
        'reminder_days',
        'reminder_date',
        'title',
        'message',
        'is_sent',
        'sent_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reminder_date' => 'date',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}