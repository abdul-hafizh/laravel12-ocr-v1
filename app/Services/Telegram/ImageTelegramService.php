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

            if ($scanType === 'electricity') {
                $kwh = $validation['data']['kwh'] ?? null;

                if (!$kwh || !$this->isValidKwh($kwh)) {
                    SendTelegram::sendMessage(
                        $chatId,
                        "❌ Nilai kWh pada layar LCD tidak terbaca jelas.\n\n" .
                        "Foto tidak disimpan ke database.\n\n" .
                        "Silakan foto ulang dengan jarak dekat, dan hindari pantulan cahaya."
                    );

                    return;
                }

                $validation['data']['kwh'] = $this->normalizeKwh($kwh);
            }

            if (in_array($scanType, ['printer', 'cea', 'asaba'], true)) {
                $serialNumber = $validation['data']['serial_number'] ?? null;

                if (!$serialNumber) {
                    SendTelegram::sendMessage(
                        $chatId,
                        "❌ Serial number tidak ditemukan pada gambar.\n\n" .
                        "Silakan kirim gambar counter mesin yang menampilkan serial number."
                    );
                    return;
                }

                $mesin = MasterMesin::where('serial_number', $serialNumber)
                    ->where('is_active', true)
                    ->first();

                if (!$mesin) {
                    SendTelegram::sendMessage(
                        $chatId,
                        "❌ Serial number <b>{$serialNumber}</b> tidak ditemukan di Master Mesin.\n\n" .
                        "Silakan daftarkan mesin terlebih dahulu."
                    );
                    return;
                }

                $validation['data']['master_mesin'] = [
                    'id' => $mesin->id,
                    'nama_mesin' => $mesin->nama_mesin,
                    'serial_number' => $mesin->serial_number,
                ];

                if ($scanType === 'printer') {
                    $bw = (int) ($validation['data']['total_bw'] ?? 0);
                    $color = (int) ($validation['data']['total_color'] ?? 0);
                    $longSheet = (int) ($validation['data']['total_long_sheet'] ?? 0);

                    $validation['data']['perhitungan'] = [
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
                    'data_penting' => [
                        'nominal_chat' => $nominalChat,
                        'telegram_chat_id' => $chatId,
                    ],
                ],
            ]);

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
        $parts = array_map('trim', explode(',', $message));

        if (count($parts) < 2) {
            SendTelegram::sendMessage(
                $chatId,
                "Format tidak valid.\n\nContoh:\n12345678901, 25.60"
            );
            return;
        }

        $nomorMeter = preg_replace('/[^0-9]/', '', $parts[0]);
        $kwh = str_replace(',', '.', $parts[1]);

        if (!$nomorMeter || !$this->isValidKwh($kwh)) {
            SendTelegram::sendMessage(
                $chatId,
                "Nomor meter atau kWh tidak valid.\n\nContoh:\n12345678901, 25.60"
            );
            return;
        }

        $masterToken = MasterTokenListrik::where('nomor_meter', $nomorMeter)
            ->where('is_active', true)
            ->first();

        if (!$masterToken) {
            SendTelegram::sendMessage(
                $chatId,
                "❌ Nomor meter masih tidak ditemukan di database.\n\n" .
                "Silakan periksa kembali nomor meter yang Anda masukkan.\n\n" .
                "Balas dengan format:\n" .
                "<nomor meter>, <kWh>\n\n" .
                "Contoh:\n" .
                "12345678901, 25.60\n\n" .
                "👇 Pesan berikut dapat langsung Anda salin (copy)."
            );

            SendTelegram::sendMessage(
                $chatId,
                "{$nomorMeter}, {$this->normalizeKwh($kwh)}"
            );

            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendTelegram::sendMessage(
                $chatId,
                "Data scan terakhir tidak ditemukan. Silakan ulangi upload foto."
            );
            return;
        }

        $analysis = is_array($scan->analysis_result)
            ? $scan->analysis_result
            : [];

        $dataPenting = $analysis['data_penting'] ?? [];

        $dataPenting['nomor_meter_lama_ocr'] = $dataPenting['nomor_meter'] ?? null;
        $dataPenting['kwh_lama_ocr'] = $dataPenting['kwh'] ?? null;
        $dataPenting['nomor_meter'] = $nomorMeter;
        $dataPenting['kwh'] = $this->normalizeKwh($kwh);
        $dataPenting['is_manual_correction'] = true;
        $dataPenting['corrected_at'] = now()->toDateTimeString();

        $analysis['data_penting'] = $dataPenting;

        $scan->update([
            'analysis_result' => $analysis,
            'status' => 'success',
            'error_message' => null,
        ]);

        $this->resetToMenu($chatId);

        SendTelegram::sendMessage(
            $chatId,
            "✅ Data token listrik berhasil diperbaiki dan disimpan.\n\n" .
            "Nomor Meter: <b>{$nomorMeter}</b>\n" .
            "kWh: <b>" . $this->normalizeKwh($kwh) . "</b>\n\n" .
            "Ketik <b>MENU</b> untuk kembali ke menu."
        );
    }

    private function handleMachineSerialCorrection(string|int $chatId, string $message, object $session): void
    {
        $serialNumber = strtoupper(trim($message));

        if ($serialNumber === '') {
            SendTelegram::sendMessage(
                $chatId,
                "Serial number tidak valid.\n\nContoh:\nABC123456"
            );
            return;
        }

        $mesin = MasterMesin::where('serial_number', $serialNumber)
            ->where('is_active', true)
            ->first();

        if (!$mesin) {
            SendTelegram::sendMessage(
                $chatId,
                "❌ Serial number tetap tidak ditemukan di database.\n\n" .
                "Silakan copy serial number pada pesan berikut, perbaiki jika ada yang salah, kemudian kirim ulang serial number yang benar."
            );

            SendTelegram::sendMessage($chatId, $serialNumber);
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendTelegram::sendMessage(
                $chatId,
                "Data scan terakhir tidak ditemukan. Silakan ulangi upload foto."
            );
            return;
        }

        $analysis = is_array($scan->analysis_result)
            ? $scan->analysis_result
            : [];

        $dataPenting = $analysis['data_penting'] ?? [];

        $dataPenting['serial_number_lama_ocr'] = $dataPenting['serial_number'] ?? null;
        $dataPenting['serial_number'] = $serialNumber;
        $dataPenting['master_mesin'] = [
            'id' => $mesin->id,
            'nama_mesin' => $mesin->nama_mesin,
            'serial_number' => $mesin->serial_number,
        ];
        $dataPenting['is_manual_correction'] = true;
        $dataPenting['corrected_at'] = now()->toDateTimeString();

        $analysis['data_penting'] = $dataPenting;

        $scan->update([
            'master_mesin_id' => $mesin->id,
            'analysis_result' => $analysis,
            'status' => 'success',
            'error_message' => null,
        ]);

        $this->resetToMenu($chatId);

        SendTelegram::sendMessage(
            $chatId,
            "✅ Data mesin berhasil diperbaiki dan disimpan.\n\n" .
            "Mesin: <b>{$mesin->nama_mesin}</b>\n" .
            "Serial Number: <b>{$mesin->serial_number}</b>\n\n" .
            "Ketik <b>MENU</b> untuk kembali ke menu."
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