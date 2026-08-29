<?php

namespace App\Services\Telegram;

use App\Jobs\AnalyzeImageJob;
use App\Libraries\SendTelegram;
use App\Models\ImageScan;
use App\Models\MasterMesin;
use App\Models\MasterTokenListrik;
use App\Services\ImageAnalysisPromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageTelegramService
{
    private const ASTRA_VENDOR_ID = 6;

    public function start(string|int $chatId, string $menu, string $scanType): void
    {
        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
            'menu' => $menu,
            'scan_type' => $scanType,
            'step' => 'ASK_IMAGE',
            'updated_at' => now(),
        ]);

        SendTelegram::sendMessage(
            $chatId,
            "Menu <b>{$menu}</b> dipilih ✅\n\n" .
            "Silakan kirim gambar/foto untuk dianalisis."
        );
    }

    public function handle(string|int $chatId, string $message, object $session, array $payload): void
    {
        if (($session->step ?? '') === 'ASK_ELECTRICITY_CORRECTION') {
            $this->handleElectricityCorrection($chatId, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_KWH_CORRECTION') {
            $this->handleKwhCorrection($chatId, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_MACHINE_SERIAL_CORRECTION') {
            $this->handleMachineSerialCorrection($chatId, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_MACHINE') {
            $this->handleMachineSelection($chatId, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_PART') {
            $this->handlePartSelection($chatId, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_CEA_TINTA') {
            $this->handleCeaTinta($chatId, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_PART_MAINTENANCE_NOMINAL') {
            $this->handlePartMaintenanceNominal($chatId, $message, $session);
            return;
        }

        $telegramMessage = $payload['message'] ?? [];

        if (empty($telegramMessage['photo']) && empty($telegramMessage['document'])) {
            SendTelegram::sendMessage(
                $chatId,
                "Silakan kirim gambar/foto, bukan teks.\n\n" .
                "Ketik <b>MENU</b> untuk kembali ke menu."
            );
            return;
        }

        $scanType = $session->scan_type ?? 'printer';

        try {
            $file = $this->downloadTelegramImage($telegramMessage);

            if (!$file) {
                SendTelegram::sendMessage($chatId, "Gagal mengambil gambar dari Telegram.");
                return;
            }

            $imageBody = $file['body'];
            $contentType = $file['mime_type'];

            $validation = $this->validateImageByScanType(
                scanType: $scanType,
                imageBody: $imageBody,
                mimeType: $contentType
            );

            if (!($validation['valid'] ?? false)) {
                SendTelegram::sendMessage(
                    $chatId,
                    "❌ Gambar tidak sesuai dengan menu yang dipilih.\n\n" .
                    "Alasan: " . ($validation['message'] ?? 'Data wajib tidak ditemukan.') . "\n\n" .
                    "Silakan kirim gambar yang sesuai.\n" .
                    "Ketik <b>MENU</b> untuk kembali ke menu."
                );
                return;
            }

            $extension = $this->extensionFromMime($contentType);
            $filename = 'telegram_' . $chatId . '_' . time() . '.' . $extension;
            $path = 'image-scans/' . $filename;

            Storage::disk('public')->put($path, $imageBody);

            $nominalChat = null;

            if ($scanType === 'part_maintenance') {
                $nominalChat = $this->extractNominalFromMessage($message);
            }

            $scan = ImageScan::create([
                'user_id' => $session->user_id ?? null,
                'cabang_id' => $session->cabang_id ?? null,
                'master_mesin_id' => $session->master_mesin_id ?? null,
                'master_mesin_part_id' => $session->master_mesin_part_id ?? null,
                'scan_type' => $scanType,
                'image_path' => $path,
                'original_filename' => $filename,
                'mime_type' => $contentType,
                'status' => 'pending',
                'analysis_result' => [
                    'valid' => $validation['valid'] ?? true,
                    'message' => $validation['message'] ?? null,
                    'data_penting' => array_merge(
                        $validation['data'] ?? [],
                        [
                            'nominal_chat' => $nominalChat,
                            'telegram_chat_id' => $chatId,
                        ]
                    ),
                ],
            ]);

            if ($scanType === 'electricity') {
                $nomorMeter = preg_replace('/[^0-9]/', '', (string) ($validation['data']['nomor_meter'] ?? ''));
                $kwh = $validation['data']['kwh'] ?? null;
                $kwhValid = $kwh && $this->isValidKwh($kwh);

                $masterToken = null;
                if ($nomorMeter) {
                    $masterToken = MasterTokenListrik::where('nomor_meter', $nomorMeter)
                        ->where('is_active', true)
                        ->first();
                }

                if (!$nomorMeter || !$masterToken) {
                    DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                        'step' => 'ASK_ELECTRICITY_CORRECTION',
                        'last_image_scan_id' => $scan->id,
                        'updated_at' => now(),
                    ]);

                    $scan->update([
                        'status' => 'failed',
                        'error_message' => 'Nomor meter tidak ditemukan di database.',
                    ]);

                    $tokens = MasterTokenListrik::where('master_cabang_id', $session->cabang_id)
                        ->where('is_active', true)
                        ->orderBy('nomor_meter')
                        ->get();

                    if ($tokens->isEmpty()) {
                        SendTelegram::sendMessage($chatId, "❌ Data token listrik gagal dideteksi, dan tidak ada data aktif untuk cabang Anda.\n\nSilakan hubungi admin.");
                        $this->resetToMenu($chatId);
                        return;
                    }

                    $text = "❌ Nomor meter tidak terdeteksi otomatis dengan benar.\n\nSilakan pilih Nomor Meter cabang Anda:\n\n";
                    foreach ($tokens as $index => $token) {
                        $no = $index + 1;
                        $text .= "<b>{$no}</b>. {$token->nomor_meter} ({$token->nama_pelanggan})\n";
                    }
                    $text .= "\n<b>0</b>. Hubungi Admin\n\nKetik nomor pilihan Anda.";
                    SendTelegram::sendMessage($chatId, $text);
                    return;
                }

                if (!$kwhValid) {
                    DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                        'step' => 'ASK_KWH_CORRECTION',
                        'last_image_scan_id' => $scan->id,
                        'updated_at' => now(),
                    ]);

                    $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
                    $analysis['data_penting']['nomor_meter'] = $nomorMeter;
                    $analysis['data_penting']['master_token_listrik'] = ['id' => $masterToken->id, 'nomor_meter' => $masterToken->nomor_meter];
                    
                    $scan->update([
                        'status' => 'failed',
                        'analysis_result' => $analysis,
                        'error_message' => 'kWh tidak terbaca jelas.',
                    ]);

                    SendTelegram::sendMessage(
                        $chatId,
                        "ℹ️ Nomor Meter terdeteksi: <b>{$nomorMeter}</b>\n" .
                        "❌ Namun angka kWh tidak terbaca jelas.\n\n" .
                        "Silakan <b>ketik/input angka kWh</b> yang tertera pada meteran saat ini (Contoh: 125.50):"
                    );
                    return;
                }

                $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
                $analysis['data_penting']['nomor_meter'] = $nomorMeter;
                $analysis['data_penting']['kwh'] = $this->normalizeKwh($kwh);
                $analysis['data_penting']['master_token_listrik'] = ['id' => $masterToken->id, 'nomor_meter' => $masterToken->nomor_meter];

                $scan->update(['analysis_result' => $analysis]);
            }

            if (in_array($scanType, ['printer', 'cea', 'asaba', 'astra'], true)) {
                $serialNumber = trim((string) ($validation['data']['serial_number'] ?? ''));
                $vendorId = $scanType === 'astra' ? self::ASTRA_VENDOR_ID : null;

                // Cari mesin berdasarkan serial number hasil OCR
                $mesin = null;
                if ($serialNumber !== '') {
                    $mesin = MasterMesin::where('serial_number', $serialNumber)
                        ->where('is_active', true)
                        ->when($vendorId, fn ($q) => $q->where('master_vendor_id', $vendorId))
                        ->first();
                }

                // JIKA serial number tidak terbaca ATAU tidak ditemukan di database
                if (!$mesin) {
                    DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                        'step' => 'ASK_MACHINE_SERIAL_CORRECTION',
                        'last_image_scan_id' => $scan->id,
                        'updated_at' => now(),
                    ]);

                    $scan->update([
                        'status' => 'failed',
                        'error_message' => 'Serial number mesin tidak ditemukan di database.',
                    ]);

                    // Ambil semua mesin yang aktif di cabang user saat ini
                    $machines = MasterMesin::where('master_cabang_id', $session->cabang_id)
                        ->where('is_active', true)
                        ->when($vendorId, fn ($q) => $q->where('master_vendor_id', $vendorId))
                        ->orderBy('nama_mesin')
                        ->get();

                    if ($machines->isEmpty()) {
                        SendTelegram::sendMessage(
                            $chatId,
                            "❌ Data mesin gagal dideteksi otomatis, dan tidak ditemukan data mesin aktif untuk cabang Anda.\n\nSilakan hubungi admin."
                        );
                        $this->resetToMenu($chatId);
                        return;
                    }

                    $text = "❌ Serial Number mesin tidak terdeteksi otomatis dengan benar.\n\n";
                    $text .= "Silakan pilih Mesin yang sesuai untuk cabang Anda:\n\n";

                    foreach ($machines as $index => $m) {
                        $no = $index + 1;
                        $text .= "<b>{$no}</b>. {$m->nama_mesin} (SN: {$m->serial_number})\n";
                    }

                    $text .= "\n<b>0</b>. Hubungi Admin (Tidak ada di list)\n\n";
                    $text .= "Ketik nomor pilihan Anda.";

                    SendTelegram::sendMessage($chatId, $text);
                    return;
                }

                $scan->update([
                    'master_mesin_id' => $mesin->id,
                ]);

                $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
                $dataPenting = $analysis['data_penting'] ?? [];

                $dataPenting['serial_number'] = $serialNumber;
                $dataPenting['master_mesin'] = [
                    'id' => $mesin->id,
                    'nama_mesin' => $mesin->nama_mesin,
                    'serial_number' => $mesin->serial_number,
                ];

                if ($scanType === 'printer') {
                    $bw = (int) ($validation['data']['total_bw'] ?? 0);
                    $color = (int) ($validation['data']['total_color'] ?? 0);
                    $longSheet = (int) ($validation['data']['total_long_sheet'] ?? 0);

                    $dataPenting['perhitungan'] = [
                        'bw' => [
                            'qty' => $bw,
                            'harga' => (int) $mesin->harga_bw,
                            'subtotal' => $bw * (int) $mesin->harga_bw,
                        ],
                        'color' => [
                            'qty' => $color,
                            'harga' => (int) $mesin->harga_color,
                            'subtotal' => $color * (int) $mesin->harga_color,
                        ],
                        'long_sheet' => [
                            'qty' => $longSheet,
                            'harga' => (int) $mesin->harga_long_sheet,
                            'subtotal' => $longSheet * (int) $mesin->harga_long_sheet,
                        ],
                        'total' =>
                            ($bw * (int) $mesin->harga_bw) +
                            ($color * (int) $mesin->harga_color) +
                            ($longSheet * (int) $mesin->harga_long_sheet),
                    ];
                }

                $analysis['data_penting'] = $dataPenting;

                $scan->update([
                    'master_mesin_id' => $mesin->id,
                    'analysis_result' => $analysis,
                ]);
            }

            AnalyzeImageJob::dispatch($scan->id);

            if ($scanType === 'cea') {
                DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                    'step' => 'ASK_CEA_TINTA',
                    'last_image_scan_id' => $scan->id,
                    'updated_at' => now(),
                ]);

                SendTelegram::sendMessage(
                    $chatId,
                    "✅ Foto CEA berhasil diterima.\n" .
                    "Sedang dianalisis oleh sistem.\n\n" .
                    "ID Scan: <b>{$scan->id}</b>\n\n" .
                    "Silakan masukkan nominal <b>biaya tinta</b>.\n" .
                    "Contoh: 185000"
                );

                return;
            }

            if ($scanType === 'part_maintenance') {
                DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                    'step' => 'ASK_PART_MAINTENANCE_NOMINAL',
                    'last_image_scan_id' => $scan->id,
                    'updated_at' => now(),
                ]);

                SendTelegram::sendMessage(
                    $chatId,
                    "✅ Foto bukti berhasil diterima.\n" .
                    "Foto disimpan sebagai arsip/bukti.\n\n" .
                    "ID Scan: <b>{$scan->id}</b>\n\n" .
                    "Silakan masukkan biaya part atau maintenance.\n" .
                    "Contoh: 25000"
                );

                return;
            }

            DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                'menu' => null,
                'scan_type' => null,
                'step' => 'ASK_MENU',
                'master_mesin_id' => null,
                'master_mesin_part_id' => null,
                'last_image_scan_id' => $scan->id,
                'updated_at' => now(),
            ]);

            $namaCabang = null;

            if (!empty($session->cabang_id)) {
                $namaCabang = DB::table('dbo.master_cabangs')
                    ->where('id', $session->cabang_id)
                    ->value('nama_cabang');
            }

            SendTelegram::sendMessage(
                $chatId,
                "✅ Gambar berhasil diterima.\n" .
                "Sedang dianalisis oleh sistem.\n\n" .
                "ID Scan: <b>{$scan->id}</b>\n" .
                "Cabang: <b>" . ($namaCabang ?: 'Belum terdeteksi') . "</b>\n\n" .
                "Silakan cek hasilnya di website.\n\n" .
                "Ketik <b>MENU</b> untuk kembali ke menu."
            );
        } catch (\Throwable $e) {
            Log::error('TELEGRAM_IMAGE_UPLOAD_ERR', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            SendTelegram::sendMessage(
                $chatId,
                "Terjadi error saat memproses gambar.\n\n" .
                "Error: " . e($e->getMessage())
            );
        }
    }

    public function startWithMachineSelection(string|int $chatId, string $menu, string $scanType): void
    {
        $session = DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->first();

        if (!$session || !$session->cabang_id) {
            SendTelegram::sendMessage(
                $chatId,
                "❌ Cabang Anda belum terdeteksi.\nSilakan hubungi admin."
            );
            return;
        }

        $mesins = MasterMesin::where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->orderBy('nama_mesin')
            ->get();

        if ($mesins->isEmpty()) {
            SendTelegram::sendMessage(
                $chatId,
                "❌ Belum ada mesin aktif untuk cabang Anda."
            );
            return;
        }

        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
            'menu' => $menu,
            'scan_type' => $scanType,
            'step' => 'ASK_MACHINE',
            'master_mesin_id' => null,
            'master_mesin_part_id' => null,
            'updated_at' => now(),
        ]);

        $text = "Menu <b>{$menu}</b> dipilih ✅\n\n";
        $text .= "Silakan pilih mesin:\n\n";

        foreach ($mesins as $index => $mesin) {
            $no = $index + 1;
            $text .= "<b>{$no}</b> {$mesin->nama_mesin}\n";
            $text .= "SN: {$mesin->serial_number}\n\n";
        }

        $text .= "Pilih nomor.";

        SendTelegram::sendMessage($chatId, $text);
    }

    private function downloadTelegramImage(array $message): ?array
    {
        $token = config('services.telegram.bot_token');

        $fileId = null;
        $mimeType = 'image/jpeg';

        if (!empty($message['photo'])) {
            $photos = $message['photo'];
            $largestPhoto = end($photos);
            $fileId = $largestPhoto['file_id'] ?? null;
            $mimeType = 'image/jpeg';
        }

        if (!$fileId && !empty($message['document'])) {
            $document = $message['document'];

            $docMime = $document['mime_type'] ?? '';

            if ($docMime && !str_starts_with($docMime, 'image/')) {
                return null;
            }

            $fileId = $document['file_id'] ?? null;
            $mimeType = $docMime && str_starts_with($docMime, 'image/')
                ? $docMime
                : 'image/jpeg';
        }

        if (!$fileId) {
            return null;
        }

        $fileResponse = Http::timeout(60)
            ->get("https://api.telegram.org/bot{$token}/getFile", [
                'file_id' => $fileId,
            ]);

        if ($fileResponse->failed()) {
            return null;
        }

        $filePath = $fileResponse->json('result.file_path');

        if (!$filePath) {
            return null;
        }

        $downloadResponse = Http::timeout(60)
            ->get("https://api.telegram.org/file/bot{$token}/{$filePath}");

        if ($downloadResponse->failed()) {
            return null;
        }

        $headerMime = $downloadResponse->header('Content-Type');
        $finalMime = $headerMime ?: $mimeType;

        if ($finalMime === 'application/octet-stream') {
            $finalMime = $mimeType;
        }

        if (!str_starts_with($finalMime, 'image/')) {
            $finalMime = 'image/jpeg';
        }

        return [
            'body' => $downloadResponse->body(),
            'mime_type' => $finalMime,
        ];
    }

    private function validateImageByScanType(string $scanType, string $imageBody, string $mimeType): array
    {
        $base64 = base64_encode($imageBody);

        $response = Http::timeout(120)
            ->withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.4-mini'),
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => ImageAnalysisPromptService::getPrompt($scanType),
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => "data:{$mimeType};base64,{$base64}",
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('Validasi gambar gagal: ' . $response->body());
        }

        $json = $response->json();
        $text = $json['output'][0]['content'][0]['text'] ?? null;

        $parsed = json_decode($text, true);

        if (
            isset($parsed['data']['nominal']) &&
            !is_numeric($parsed['data']['nominal'])
        ) {
            $parsed['data']['nominal'] = (int) preg_replace(
                '/[^0-9]/',
                '',
                (string) $parsed['data']['nominal']
            );
        }

        if (!is_array($parsed)) {
            return [
                'valid' => false,
                'message' => 'Sistem tidak dapat membaca isi gambar dengan jelas.',
            ];
        }

        return [
            'valid' => (bool)($parsed['valid'] ?? false),
            'message' => $parsed['message'] ?? 'Data wajib tidak ditemukan.',
            'data' => $parsed['data_penting'] ?? [],
        ];
    }

    private function handleMachineSelection(string|int $chatId, string $message, object $session): void
    {
        $choice = (int) trim($message);

        if ($choice <= 0) {
            SendTelegram::sendMessage($chatId, "Silakan ketik nomor mesin yang valid.");
            return;
        }

        $mesins = MasterMesin::where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->orderBy('nama_mesin')
            ->get();

        $mesin = $mesins->get($choice - 1);

        if (!$mesin) {
            SendTelegram::sendMessage($chatId, "Nomor mesin tidak ditemukan. Silakan pilih ulang.");
            return;
        }

        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
            'master_mesin_id' => $mesin->id,
            'updated_at' => now(),
        ]);

        if ($session->menu === 'BIAYA_PART') {
            $parts = $mesin->maintenanceParts()
                ->orderBy('nama_part')
                ->get();

            if ($parts->isEmpty()) {
                SendTelegram::sendMessage(
                    $chatId,
                    "❌ Mesin <b>{$mesin->nama_mesin}</b> belum memiliki data part."
                );
                return;
            }

            DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                'step' => 'ASK_PART',
                'updated_at' => now(),
            ]);

            $text = "Mesin dipilih ✅\n";
            $text .= "<b>{$mesin->nama_mesin}</b>\n";
            $text .= "SN: {$mesin->serial_number}\n\n";
            $text .= "Silakan pilih part:\n\n";

            foreach ($parts as $index => $part) {
                $no = $index + 1;
                $text .= "<b>{$no}</b> {$part->nama_part}\n";
            }

            $text .= "\nKetik nomor part.";

            SendTelegram::sendMessage($chatId, $text);
            return;
        }

        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
            'step' => 'ASK_IMAGE',
            'master_mesin_part_id' => null,
            'updated_at' => now(),
        ]);

        SendTelegram::sendMessage(
            $chatId,
            "Mesin dipilih ✅\n\n" .
            "<b>{$mesin->nama_mesin}</b>\n" .
            "SN: {$mesin->serial_number}\n\n" .
            "Silakan kirim gambar/foto bukti maintenance mesin."
        );
    }

    private function handlePartSelection(string|int $chatId, string $message, object $session): void
    {
        $choice = (int) trim($message);

        if ($choice <= 0) {
            SendTelegram::sendMessage($chatId, "Silakan ketik nomor part yang valid.");
            return;
        }

        $mesin = MasterMesin::with('maintenanceParts')
            ->where('id', $session->master_mesin_id)
            ->where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->first();

        if (!$mesin) {
            SendTelegram::sendMessage($chatId, "❌ Mesin tidak valid. Ketik <b>MENU</b> untuk kembali ke menu.");
            return;
        }

        $parts = $mesin->maintenanceParts()
            ->orderBy('nama_part')
            ->get();

        $part = $parts->get($choice - 1);

        if (!$part) {
            SendTelegram::sendMessage($chatId, "Nomor part tidak ditemukan. Silakan pilih ulang.");
            return;
        }

        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
            'master_mesin_part_id' => $part->id,
            'step' => 'ASK_IMAGE',
            'updated_at' => now(),
        ]);

        SendTelegram::sendMessage(
            $chatId,
            "Part dipilih ✅\n\n" .
            "Mesin: <b>{$mesin->nama_mesin}</b>\n" .
            "SN: {$mesin->serial_number}\n" .
            "Part: <b>{$part->nama_part}</b>\n\n" .
            "Silakan kirim gambar/foto bukti biaya part."
        );
    }

    private function handleCeaTinta(string|int $chatId, string $message, object $session): void
    {
        $biayaTinta = $this->extractNominalFromMessage($message);

        if ($biayaTinta === null) {
            SendTelegram::sendMessage(
                $chatId,
                "Nominal tinta tidak valid.\n\nContoh: 185000"
            );
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendTelegram::sendMessage(
                $chatId,
                "Data foto CEA terakhir tidak ditemukan. Silakan ulangi upload foto."
            );
            return;
        }

        $serialNumber =
            $scan->analysis_result['data_penting']['serial_number']
            ?? $scan->analysis_result['data']['serial_number']
            ?? null;

        if (!$serialNumber) {
            $serialNumber = DB::table('dbo.master_mesins')
                ->where('id', $scan->master_mesin_id)
                ->value('serial_number');
        }

        [$periodeStart, $periodeEnd] = $this->getBillingPeriod($scan->created_at);

        $isLocked = DB::table('dbo.cea_billing_costs')
            ->where('periode_start', $periodeStart->toDateString())
            ->where('periode_end', $periodeEnd->toDateString())
            ->where('cabang_id', $scan->cabang_id)
            ->where('serial_number', $serialNumber)
            ->whereNotNull('locked_at')
            ->exists();

        if ($isLocked) {
            $this->resetToMenu($chatId);

            SendTelegram::sendMessage(
                $chatId,
                "Periode " . $periodeStart->format('d/m/Y') . " - " . $periodeEnd->format('d/m/Y') .
                " sudah dilaporkan dan terkunci. Hubungi admin jika perlu koreksi."
            );

            return;
        }

        DB::table('dbo.cea_billing_costs')->updateOrInsert(
            [
                'periode_start' => $periodeStart->toDateString(),
                'periode_end' => $periodeEnd->toDateString(),
                'cabang_id' => $scan->cabang_id,
                'serial_number' => $serialNumber,
            ],
            [
                'image_scan_id' => $scan->id,
                'master_mesin_id' => $scan->master_mesin_id,
                'contract_service' => 300000,
                'biaya_tinta' => $biayaTinta,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->resetToMenu($chatId);

        SendTelegram::sendMessage(
            $chatId,
            "✅ Biaya tinta CEA berhasil disimpan.\n\n" .
            "Tinta: Rp " . number_format($biayaTinta, 0, ',', '.') . "\n" .
            "Periode: " . $periodeStart->format('d/m/Y') . " - " . $periodeEnd->format('d/m/Y') . "\n\n" .
            "Ketik <b>MENU</b> untuk kembali ke menu."
        );
    }

    private function handlePartMaintenanceNominal(string|int $chatId, string $message, object $session): void
    {
        $nominal = $this->extractNominalFromMessage($message);

        if ($nominal === null) {
            SendTelegram::sendMessage(
                $chatId,
                "Nominal tidak valid.\n\nContoh: 250000"
            );
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendTelegram::sendMessage(
                $chatId,
                "Data foto terakhir tidak ditemukan. Silakan ulangi upload foto."
            );
            return;
        }

        $mesin = MasterMesin::find($scan->master_mesin_id);

        if (!$mesin) {
            SendTelegram::sendMessage($chatId, "Data mesin tidak ditemukan.");
            return;
        }

        [$periodeStart, $periodeEnd] = $this->getBillingPeriod($scan->created_at);

        $isPart = $session->menu === 'BIAYA_PART';

        $namaPart = null;

        if ($isPart && $scan->master_mesin_part_id) {
            $namaPart = DB::table('dbo.master_mesin_parts')
                ->where('id', $scan->master_mesin_part_id)
                ->value('nama_part');
        }

        $isLocked = DB::table('dbo.machine_maintenance_costs')
            ->where('periode_start', $periodeStart->toDateString())
            ->where('periode_end', $periodeEnd->toDateString())
            ->where('cabang_id', $scan->cabang_id)
            ->where('master_mesin_id', $scan->master_mesin_id)
            ->whereNotNull('locked_at')
            ->exists();

        if ($isLocked) {
            $this->resetToMenu($chatId);

            SendTelegram::sendMessage(
                $chatId,
                "Periode ini sudah dilaporkan dan terkunci untuk mesin ini. Hubungi admin jika perlu koreksi."
            );

            return;
        }

        DB::table('dbo.machine_maintenance_costs')->insert([
            'periode_start' => $periodeStart->toDateString(),
            'periode_end' => $periodeEnd->toDateString(),
            'image_scan_id' => $scan->id,
            'cabang_id' => $scan->cabang_id,
            'master_mesin_id' => $scan->master_mesin_id,
            'serial_number' => $mesin->serial_number,
            'cost_type' => $isPart ? 'part' : 'maintenance',
            'nama_part' => $isPart ? $namaPart : null,
            'nominal' => $nominal,
            'keterangan' => $isPart
                ? 'Biaya part mesin' . ($namaPart ? ': ' . $namaPart : '')
                : 'Biaya maintenance mesin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->resetToMenu($chatId);

        SendTelegram::sendMessage(
            $chatId,
            "✅ Biaya berhasil disimpan.\n\n" .
            "Jenis: <b>" . ($isPart ? "Biaya Part" : "Biaya Maintenance") . "</b>\n" .
            "Nominal: Rp " . number_format($nominal, 0, ',', '.') . "\n" .
            "Periode: " . $periodeStart->format('d/m/Y') . " - " . $periodeEnd->format('d/m/Y') . "\n\n" .
            "Ketik <b>MENU</b> untuk kembali ke menu."
        );
    }

    private function handleElectricityCorrection(string|int $chatId, string $message, object $session): void
    {
        $choice = trim($message);

        if ($choice === '0') {
            SendTelegram::sendMessage($chatId, "Silakan hubungi admin.\n\nKetik <b>MENU</b> untuk kembali.");
            $this->resetToMenu($chatId);
            return;
        }

        $choiceIndex = (int) $choice;
        $tokens = MasterTokenListrik::where('master_cabang_id', $session->cabang_id)->where('is_active', true)->orderBy('nomor_meter')->get();
        $selectedToken = $tokens->get($choiceIndex - 1);

        if (!$selectedToken) {
            SendTelegram::sendMessage($chatId, "❌ Pilihan tidak valid.");
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);
        $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
        $dataPenting = $analysis['data_penting'] ?? [];

        $kwh = $dataPenting['kwh'] ?? null;
        $kwhValid = $kwh && $this->isValidKwh($kwh);

        $dataPenting['nomor_meter'] = $selectedToken->nomor_meter;
        $dataPenting['master_token_listrik'] = ['id' => $selectedToken->id, 'nomor_meter' => $selectedToken->nomor_meter];
        $dataPenting['is_manual_correction'] = true;
        
        $analysis['data_penting'] = $dataPenting;
        $scan->update(['analysis_result' => $analysis]);

        if (!$kwhValid) {
            DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
                'step' => 'ASK_KWH_CORRECTION',
                'updated_at' => now(),
            ]);

            SendTelegram::sendMessage(
                $chatId,
                "✅ Nomor Meter dipilih: <b>{$selectedToken->nomor_meter}</b>\n\n" .
                "📝 Langkah terakhir, angka kWh tidak terbaca jelas. Silakan <b>ketik langsung nilai kWh saat ini</b> (Contoh: 250.75):"
            );
            return;
        }

        $dataPenting['kwh'] = $this->normalizeKwh($kwh);
        $analysis['data_penting'] = $dataPenting;

        $scan->update([
            'analysis_result' => $analysis,
            'status' => 'success',
            'error_message' => null,
        ]);

        AnalyzeImageJob::dispatch($scan->id);
        $this->resetToMenu($chatId);
        SendTelegram::sendMessage($chatId, "✅ Data berhasil disimpan.\nMeter: <b>{$selectedToken->nomor_meter}</b>\nkWh: <b>{$dataPenting['kwh']}</b>");
    }

    private function handleKwhCorrection(string|int $chatId, string $message, object $session): void
    {
        $inputKwh = trim($message);
        
        // 1. Bersihkan spasi dan standarisasi koma menjadi titik
        $inputKwh = str_replace(',', '.', $inputKwh); 
        // Hilangkan semua karakter kecuali angka dan titik desimal
        $inputKwh = preg_replace('/[^0-9.]/', '', $inputKwh);

        if ($inputKwh === '' || !is_numeric($inputKwh) || (float)$inputKwh < 0) {
            SendTelegram::sendMessage($chatId, "⚠️ Nilai kWh tidak valid. Silakan masukkan angka yang benar (Contoh: 239.85):");
            return;
        }

        // 2. AUTO-FORMAT DESIMAL (Paling Penting!)
        // Jika user mengetik angka bulat (tidak ada karakter titik sama sekali)
        if (strpos($inputKwh, '.') === false) {
            $length = strlen($inputKwh);
            
            if ($length > 2) {
                // Ambil angka depan, lalu sisipkan titik sebelum 2 angka terakhir
                // Contoh: "23985" menjadi "239" . "." . "85" => "239.85"
                $angkaDepan = substr($inputKwh, 0, $length - 2);
                $angkaDesimal = substr($inputKwh, -2);
                $inputKwh = $angkaDepan . '.' . $angkaDesimal;
            } else {
                // Jika user cuma ketik 1 atau 2 digit (misal: "85"), kita anggap "0.85"
                $inputKwh = '0.' . str_pad($inputKwh, 2, '0', STR_PAD_LEFT);
            }
        }

        $scan = ImageScan::find($session->last_image_scan_id);
        $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
        $dataPenting = $analysis['data_penting'] ?? [];

        // Format akhir menjadi 2 digit desimal (misal: 239.85)
        $formattedKwh = number_format((float)$inputKwh, 2, '.', '');

        // Suntik kwh hasil perbaikan ke data penting
        $dataPenting['kwh'] = $formattedKwh;
        $dataPenting['is_manual_correction'] = true;
        $dataPenting['kwh_corrected_at'] = now()->toDateTimeString();

        $analysis['data_penting'] = $dataPenting;

        // Set status sukses karena data sudah lengkap dan valid
        $scan->update([
            'analysis_result' => $analysis,
            'status' => 'success',
            'error_message' => null,
        ]);

        // Lempar kembali ke Background Job untuk diproses ke database transaksi utama
        AnalyzeImageJob::dispatch($scan->id);

        $this->resetToMenu($chatId);

        SendTelegram::sendMessage(
            $chatId,
            "✅ Data kWh berhasil dimasukkan dan diformat otomatis.\n\n" .
            "Nomor Meter: <b>" . ($dataPenting['nomor_meter'] ?? '-') . "</b>\n" .
            "Nilai kWh Terformat: <b>{$formattedKwh}</b>\n\n" .
            "Ketik <b>MENU</b> untuk kembali."
        );
    }

    private function handleMachineSerialCorrection(string|int $chatId, string $message, object $session): void
    {
        $choice = trim($message);

        if ($choice === '0') {
            SendTelegram::sendMessage(
                $chatId,
                "Silakan hubungi admin untuk mendaftarkan atau memperbaiki data Serial Number mesin Anda.\n\nKetik <b>MENU</b> untuk kembali."
            );
            $this->resetToMenu($chatId);
            return;
        }

        $choiceIndex = (int) $choice;
        if ($choiceIndex <= 0) {
            SendTelegram::sendMessage($chatId, "⚠️ Pilihan tidak valid. Silakan ketik nomor urut yang sesuai atau 0.");
            return;
        }

        $vendorId = ($session->scan_type ?? null) === 'astra' ? self::ASTRA_VENDOR_ID : null;

        $machines = MasterMesin::where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->when($vendorId, fn ($q) => $q->where('master_vendor_id', $vendorId))
            ->orderBy('nama_mesin')
            ->get();

        $selectedMachine = $machines->get($choiceIndex - 1);

        if (!$selectedMachine) {
            SendTelegram::sendMessage($chatId, "❌ Nomor pilihan tidak ditemukan. Silakan pilih nomor yang tertera pada daftar.");
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendTelegram::sendMessage($chatId, "Data scan terakhir tidak ditemukan. Silakan ulangi upload foto.");
            $this->resetToMenu($chatId);
            return;
        }

        $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
        $dataPenting = $analysis['data_penting'] ?? [];

        $dataPenting['serial_number_lama_ocr'] = $dataPenting['serial_number'] ?? null;
        $dataPenting['serial_number'] = $selectedMachine->serial_number;
        $dataPenting['master_mesin'] = [
            'id' => $selectedMachine->id,
            'nama_mesin' => $selectedMachine->nama_mesin,
            'serial_number' => $selectedMachine->serial_number,
        ];
        $dataPenting['is_manual_correction'] = true;
        $dataPenting['corrected_at'] = now()->toDateTimeString();

        $analysis['data_penting'] = $dataPenting;

        $scan->update([
            'master_mesin_id' => $selectedMachine->id,
            'analysis_result' => $analysis,
            'status' => 'success',
            'error_message' => null,
        ]);

        AnalyzeImageJob::dispatch($scan->id);

        $this->resetToMenu($chatId);

        SendTelegram::sendMessage(
            $chatId,
            "✅ Data mesin berhasil dikoreksi dan disimpan.\n\n" .
            "Nama Mesin: <b>{$selectedMachine->nama_mesin}</b>\n" .
            "Serial Number: <b>{$selectedMachine->serial_number}</b>\n\n" .
            "Ketik <b>MENU</b> untuk kembali."
        );
    }

    private function resetToMenu(string|int $chatId): void
    {
        DB::table('dbo.telegram_sessions')->where('chat_id', $chatId)->update([
            'menu' => null,
            'scan_type' => null,
            'step' => 'ASK_MENU',
            'last_image_scan_id' => null,
            'master_mesin_id' => null,
            'master_mesin_part_id' => null,
            'updated_at' => now(),
        ]);
    }

    private function isValidKwh($kwh): bool
    {
        $kwh = trim((string) $kwh);
        $kwh = str_replace(',', '.', $kwh);

        return preg_match('/^\d+\.\d{2}$/', $kwh) === 1;
    }

    private function normalizeKwh($kwh): string
    {
        $kwh = trim((string) $kwh);
        $kwh = str_replace(',', '.', $kwh);

        return number_format((float) $kwh, 2, '.', '');
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function extractNominalFromMessage(?string $message): ?int
    {
        $message = trim((string) $message);

        if ($message === '') {
            return null;
        }

        preg_match('/\d[\d\.\,\s]*/', $message, $matches);

        if (!isset($matches[0])) {
            return null;
        }

        $nominal = preg_replace('/[^0-9]/', '', $matches[0]);

        if ($nominal === '') {
            return null;
        }

        return (int) $nominal;
    }

    private function getBillingPeriod($date): array
    {
        $date = \Carbon\Carbon::parse($date);

        if ((int) $date->format('d') >= 28) {
            $start = $date->copy()->day(28)->startOfDay();
            $end = $date->copy()->addMonthNoOverflow()->day(28)->endOfDay();
        } else {
            $start = $date->copy()->subMonthNoOverflow()->day(28)->startOfDay();
            $end = $date->copy()->day(28)->endOfDay();
        }

        return [$start, $end];
    }
}