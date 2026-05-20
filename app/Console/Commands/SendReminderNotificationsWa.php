<?php

namespace App\Console\Commands;

use App\Libraries\SendSms;
use App\Models\ReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReminderNotificationsWa extends Command
{
    protected $signature = 'reminder:send-wa';
    protected $description = 'Kirim semua reminder WA otomatis';

    public function handle()
    {
        $today = now()->toDateString();

        $reminders = ReminderNotification::with('user')
            ->where('is_sent', false)
            ->whereDate('reminder_date', '<=', $today)
            ->get();

        foreach ($reminders as $reminder) {
            if (!$reminder->user || !$reminder->user->phone) {
                continue;
            }

            try {
                SendSms::sendMessageWA(
                    $reminder->user->phone,
                    $reminder->message
                );

                $reminder->update([
                    'is_sent' => true,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('GENERAL_REMINDER_WA_FAILED', [
                    'reminder_id' => $reminder->id,
                    'module' => $reminder->module,
                    'reference_id' => $reminder->reference_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}