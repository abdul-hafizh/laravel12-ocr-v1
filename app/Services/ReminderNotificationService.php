<?php

namespace App\Services;

use App\Models\ReminderNotification;
use Carbon\Carbon;

class ReminderNotificationService
{
    public static function createOrUpdate(array $data): ReminderNotification
    {
        $dueDate = Carbon::parse($data['due_date']);
        $reminderDays = (int) $data['reminder_days'];
        $reminderDate = $dueDate->copy()->subDays($reminderDays);

        return ReminderNotification::updateOrCreate(
            [
                'module' => $data['module'],
                'reference_id' => $data['reference_id'],
                'user_id' => $data['user_id'],
            ],
            [
                'due_date' => $dueDate,
                'reminder_days' => $reminderDays,
                'reminder_date' => $reminderDate,
                'title' => $data['title'],
                'message' => $data['message'],
                'is_sent' => false,
                'sent_at' => null,
            ]
        );
    }
}