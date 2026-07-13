<?php

namespace App\Services\Telegram;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Libraries\SendTelegram;
use App\Models\TelegramUser;
use App\Models\User;

class TelegramRouterService
{
    public function handle(Request $request): void
    {
        $message = $request->input('message');

        if (!$message) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $telegramUserId = $message['from']['id'] ?? null;
        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;
        $lastName = $message['from']['last_name'] ?? null;

        $text = trim((string)($message['text'] ?? ''));

        $hasPhoto = isset($message['photo']);
        $hasDocument = isset($message['document']);
        $contact = $message['contact'] ?? null;

        if (!$chatId || !$telegramUserId) {
            return;
        }

        if ($text === '' && !$hasPhoto && !$hasDocument && !$contact) {
            return;
        }

        $mapping = TelegramUser::where('telegram_chat_id', $chatId)
            ->where('telegram_user_id', $telegramUserId)
            ->where('is_active', 1)
            ->first();

        if ($mapping) {

            $user = User::where('id', $mapping->user_id)
                ->where('is_active', 1)
                ->where('is_delete', 0)
                ->first();

            if (!$user) {
                SendTelegram::sendMessage(
                    $chatId,
                    "User Anda sudah tidak aktif pada sistem."
                );
                return;
            }

        } else {

            if (!$contact) {
                SendTelegram::sendContactRequest($chatId);
                return;
            }

            if (($contact['user_id'] ?? null) != $telegramUserId) {
                SendTelegram::sendMessage(
                    $chatId,
                    "Silakan bagikan nomor telepon Anda sendiri menggunakan tombol yang tersedia."
                );

                return;
            }

            $phone = preg_replace('/\D/', '', $contact['phone_number']);

            $user = User::where('phone', $phone)
                ->where('is_active', 1)
                ->where('is_delete', 0)
                ->first();

            if (!$user) {

                SendTelegram::sendMessage(
                    $chatId,
                    "Nomor telepon Anda belum terdaftar pada sistem.\n\n" .
                    "Silakan hubungi Administrator untuk mendapatkan akses."
                );

                return;
            }

            $mapping = TelegramUser::updateOrCreate(
                [
                    'telegram_chat_id' => $chatId,
                    'telegram_user_id' => $telegramUserId,
                ],
                [
                    'user_id' => $user->id,
                    'telegram_username' => $username,
                    'telegram_first_name' => $firstName,
                    'telegram_last_name' => $lastName,
                    'is_active' => true,
                ]
            );

            SendTelegram::removeKeyboard(
                $chatId,
                "✅ Nomor telepon berhasil diverifikasi.\n\nHalo {$user->name}"
            );
        }

        $cabang = DB::table('dbo.master_cabangs as c')
            ->join('dbo.master_cabang_user as mcu', 'mcu.master_cabang_id', '=', 'c.id')
            ->where('mcu.user_id', $user->id)
            ->where('c.is_active', 1)
            ->select('c.*')
            ->first();

        $cmd = strtoupper(trim($text));

        if ($cmd === 'PING') {
            SendTelegram::sendMessage($chatId, 'pong ✅ webhook Telegram aktif');
            return;
        }

        if (in_array($cmd, ['MENU', '/START', 'START', 'RESET', 'ULANG', 'CANCEL', 'BATAL'], true)) {
            $this->resetSession(
                chatId: $chatId,
                telegramUserId: $telegramUserId,
                userId: $user->id,
                cabangId: $cabang?->id
            );

            SendTelegram::sendMessage(
                $chatId,
                "Halo {$user->name} 👋\n\n" . $this->menuText($user->role_id)
            );

            return;
        }

        $session = DB::table('dbo.telegram_sessions')
            ->where('chat_id', $chatId)
            ->first();

        if (!$session) {
            DB::table('dbo.telegram_sessions')->insert([
                'chat_id' => $chatId,
                'telegram_user_id' => $telegramUserId,
                'user_id' => $user->id,
                'cabang_id' => $cabang?->id,
                'menu' => null,
                'scan_type' => null,
                'step' => 'ASK_MENU',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $session = DB::table('dbo.telegram_sessions')
                ->where('chat_id', $chatId)
                ->first();
        }

        /**
         * STEP: ASK_MENU
         */
        if (($session->step ?? '') === 'ASK_MENU') {
            $menu = $this->detectMenu($text);

            if (!$menu) {
                SendTelegram::sendMessage($chatId, $this->menuText($user->role_id));
                return;
            }

            if (!$this->userCanAccessMenu($user->role_id, $menu)) {
                SendTelegram::sendMessage(
                    $chatId,
                    "Maaf, Anda tidak memiliki akses ke menu tersebut.\n\n" .
                    $this->menuText($user->role_id)
                );

                return;
            }

            if ($menu === 'BMI') {
                try {
                    app(BmiTelegramService::class)->start($chatId);
                } catch (\Throwable $e) {
                    Log::error('TELEGRAM_BMI_MENU_ERROR', [
                        'chat_id' => $chatId,
                        'telegram_user_id' => $telegramUserId,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);

                    SendTelegram::sendMessage(
                        $chatId,
                        "❌ Error saat membuka menu BMI:\n\n" .
                        e($e->getMessage()) . "\n\n" .
                        "File: " . e($e->getFile()) . "\n" .
                        "Line: " . $e->getLine()
                    );
                }

                return;
            }

            if ($menu === 'BIAYA_UMUM') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'online_receipt');
                return;
            }

            if ($menu === 'BIAYA_TOKEN_LISTRIK') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'electricity');
                return;
            }

            if ($menu === 'BIAYA_KLIK_METER') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'printer');
                return;
            }

            if ($menu === 'MESIN_CEA') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'cea');
                return;
            }

            if ($menu === 'ASABA') {
                app(ImageTelegramService::class)->start($chatId, $menu, 'asaba');
                return;
            }

            if ($menu === 'BIAYA_PART') {
                app(ImageTelegramService::class)->startWithMachineSelection(
                    chatId: $chatId,
                    menu: $menu,
                    scanType: 'part_maintenance'
                );
                return;
            }

            if ($menu === 'MAINTENANCE_MESIN') {
                app(ImageTelegramService::class)->startWithMachineSelection(
                    chatId: $chatId,
                    menu: $menu,
                    scanType: 'part_maintenance'
                );
                return;
            }
        }

        match ($session->menu) {
            'BMI' => app(BmiTelegramService::class)->handle(
                $chatId,
                $text,
                $session,
                $request->all()
            ),

            'BIAYA_TOKEN_LISTRIK',
            'BIAYA_KLIK_METER',
            'MESIN_CEA',
            'ASABA',
            'MAINTENANCE_MESIN',
            'BIAYA_PART',
            'BIAYA_UMUM' => app(ImageTelegramService::class)->handle(
                $chatId,
                $text,
                $session,
                $request->all()
            ),

            default => SendTelegram::sendMessage(
                $chatId,
                "Ketik <b>MENU</b> untuk kembali ke menu utama."
            ),
        };
    }

    private function resetSession(
        string|int $chatId,
        string|int $telegramUserId,
        int $userId,
        ?int $cabangId
    ): void {
        DB::table('dbo.telegram_sessions')->updateOrInsert(
            ['chat_id' => $chatId],
            [
                'telegram_user_id' => $telegramUserId,
                'user_id' => $userId,
                'cabang_id' => $cabangId,
                'menu' => null,
                'scan_type' => null,
                'step' => 'ASK_MENU',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function userCanAccessMenu(?int $roleId, string $menuKey): bool
    {
        if (!$roleId) {
            return false;
        }

        return DB::table('dbo.role_whatsapp_menus')
            ->where('role_id', $roleId)
            ->where('menu_key', $menuKey)
            ->where('is_active', 1)
            ->exists();
    }

    private function menuText(?int $roleId = null): string
    {
        $menus = [
            'BMI' => '<b>1</b> BMI',
            'BIAYA_UMUM' => '<b>2</b> Biaya Umum',
            'BIAYA_TOKEN_LISTRIK' => '<b>3</b> Biaya Token Listrik',
            'BIAYA_KLIK_METER' => '<b>4</b> Mesin Samafitro',
            'MESIN_CEA' => '<b>5</b> Mesin CEA',
            'ASABA' => '<b>6</b> Mesin Asaba',
            'BIAYA_PART' => '<b>7</b> Biaya Part',
            'MAINTENANCE_MESIN' => '<b>8</b> Maintenance Mesin',
        ];

        if ($roleId) {
            $allowedMenus = DB::table('dbo.role_whatsapp_menus')
                ->where('role_id', $roleId)
                ->where('is_active', 1)
                ->pluck('menu_key')
                ->toArray();

            $menus = array_filter(
                $menus,
                fn ($label, $key) => in_array($key, $allowedMenus, true),
                ARRAY_FILTER_USE_BOTH
            );
        }

        if (empty($menus)) {
            return "Halo 👋\n\nAnda belum memiliki akses menu Telegram.\nSilakan hubungi admin.";
        }

        return
            "Silakan pilih menu:\n\n" .
            implode("\n", $menus) . "\n\n" .
            "Ketik angka menu.";
    }

    private function detectMenu(string $message): ?string
    {
        return match (strtoupper(trim($message))) {
            '1', 'BMI' => 'BMI',
            '2', 'BIAYA UMUM' => 'BIAYA_UMUM',
            '3', 'TOKEN LISTRIK', 'BIAYA TOKEN LISTRIK' => 'BIAYA_TOKEN_LISTRIK',
            '4', 'KLIK METER', 'BIAYA KLIK METER', 'MESIN SAMAFITRO', 'SAMAFITRO' => 'BIAYA_KLIK_METER',
            '5', 'MESIN CEA', 'CEA' => 'MESIN_CEA',
            '6', 'ASABA', 'MESIN ASABA' => 'ASABA',
            '7', 'PART', 'BIAYA PART' => 'BIAYA_PART',
            '8', 'MAINTENANCE', 'MAINTENANCE MESIN' => 'MAINTENANCE_MESIN',
            default => null,
        };
    }

    private function normalizePhone($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }
}