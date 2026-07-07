<?php

namespace App\Console\Commands;

use App\Libraries\SendTelegram;
use App\Models\MasterKendaraan;
use App\Models\ReminderNotification;
use App\Services\ReminderNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendReminderNotificationsTelegram extends Command
{
    protected $signature = 'reminder:send-telegram';
    protected $description = 'Kirim semua reminder Telegram otomatis';

    public function handle()
    {
        $today = now()->toDateString();

        $reminders = ReminderNotification::with('user')
            ->where('is_sent', false)
            ->whereDate('reminder_date', '<=', $today)
            ->get();

        foreach ($reminders as $reminder) {
            if (!$reminder->user) {
                continue;
            }

            $telegramUser = DB::table('dbo.telegram_users')
                ->where('user_id', $reminder->user_id)
                ->where('is_active', 1)
                ->first();

            if (!$telegramUser || !$telegramUser->telegram_chat_id) {
                Log::warning('GENERAL_REMINDER_TELEGRAM_NO_MAPPING', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $reminder->user_id,
                ]);

                continue;
            }

            try {
                SendTelegram::sendMessage(
                    $telegramUser->telegram_chat_id,
                    $reminder->message
                );

                $reminder->update([
                    'is_sent' => true,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('GENERAL_REMINDER_TELEGRAM_FAILED', [
                    'reminder_id' => $reminder->id,
                    'module' => $reminder->module,
                    'reference_id' => $reminder->reference_id,
                    'user_id' => $reminder->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->rolloverGantiKaleng();

        return Command::SUCCESS;
    }

    private function rolloverGantiKaleng(): void
    {
        $kendaraans = MasterKendaraan::with('cabang')
            ->where('is_active', true)
            ->whereNotNull('tanggal_ganti_kaleng')
            ->whereDate('tanggal_ganti_kaleng', '<', today())
            ->get();

        foreach ($kendaraans as $kendaraan) {
            $kendaraan->tanggal_ganti_kaleng = $kendaraan->tanggal_ganti_kaleng
                ->copy()
                ->addYears(5)
                ->toDateString();

            $kendaraan->save();

            $reminderDays = $kendaraan->reminder_ganti_kaleng_hari ?: [90, 60, 30];

            foreach ($kendaraan->finance_user_ids ?? [] as $userId) {
                foreach ($reminderDays as $reminderDay) {
                    ReminderNotificationService::createOrUpdate([
                        'module' => 'kendaraan_ganti_kaleng',
                        'reference_id' => $kendaraan->id,
                        'user_id' => $userId,
                        'due_date' => $kendaraan->tanggal_ganti_kaleng,
                        'reminder_days' => $reminderDay,
                        'title' => 'Reminder Ganti Kaleng Kendaraan',
                        'message' => $this->buildGantiKalengMessage($kendaraan, $reminderDay),
                    ]);
                }
            }
        }
    }

    private function buildGantiKalengMessage(MasterKendaraan $kendaraan, int $reminderDay): string
    {
        return "Halo Finance,\n\n"
            . "Reminder ganti kaleng / perpanjangan STNK 5 tahunan kendaraan.\n\n"
            . "No. Polisi: <b>{$kendaraan->nomor_polisi}</b>\n"
            . "Jenis: {$kendaraan->jenis_kendaraan}\n"
            . "Merk/Tipe: {$kendaraan->merk} {$kendaraan->tipe}\n"
            . "Cabang: " . ($kendaraan->cabang?->nama_cabang ?? '-') . "\n"
            . "Pemilik STNK: " . ($kendaraan->nama_pemilik ?: '-') . "\n"
            . "Tanggal Ganti Kaleng: " . optional($kendaraan->tanggal_ganti_kaleng)->format('d-m-Y') . "\n"
            . "Reminder: <b>H-{$reminderDay}</b>\n"
            . "Keterangan: " . ($kendaraan->keterangan ?: '-') . "\n\n"
            . "Mohon segera dilakukan pengecekan dan tindak lanjut untuk proses ganti kaleng kendaraan.\n\n"
            . "Terima kasih.";
    }
}