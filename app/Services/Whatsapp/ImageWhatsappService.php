<?php

namespace App\Services\Whatsapp;

use App\Jobs\AnalyzeImageJob;
use App\Libraries\SendSms;
use App\Models\ImageScan;
use App\Models\MasterMesin;
use App\Models\MasterTokenListrik;
use App\Services\ImageAnalysisPromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageWhatsappService
{
    public function start(string $phone, string $menu, string $scanType): void
    {
        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'menu' => $menu,
            'scan_type' => $scanType,
            'step' => 'ASK_IMAGE',
            'updated_at' => now(),
        ]);

        SendSms::sendMessageWA(
            $phone,
            "Menu *{$menu}* dipilih ✅\n\n" .
            "Silakan kirim gambar/foto untuk dianalisis."
        );
    }

    public function handle(string $phone, string $message, object $session, array $payload): void
    {
        if (($session->step ?? '') === 'ASK_ELECTRICITY_CORRECTION') {
            $this->handleElectricityCorrection($phone, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_KWH_CORRECTION') {
            $this->handleKwhCorrection($phone, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_MACHINE_SERIAL_CORRECTION') {
            $this->handleMachineSerialCorrection($phone, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_MACHINE') {
            $this->handleMachineSelection($phone, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_PART') {
            $this->handlePartSelection($phone, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_CEA_TINTA') {
            $this->handleCeaTinta($phone, $message, $session);
            return;
        }

        if (($session->step ?? '') === 'ASK_PART_MAINTENANCE_NOMINAL') {
            $this->handlePartMaintenanceNominal($phone, $message, $session);
            return;
        }

        if (($payload['messageType'] ?? null) !== 'image') {
            SendSms::sendMessageWA(
                $phone,
                "Silakan kirim gambar/foto, bukan teks.\n\n" .
                "Ketik *ulang* untuk kembali ke menu."
            );
            return;
        }

        $scanType = $session->scan_type ?? 'printer';

        try {
            $file = $this->downloadWhatsappImage($payload);

            if (!$file) {
                SendSms::sendMessageWA($phone, "Gagal mengambil gambar dari WhatsApp.");
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
                SendSms::sendMessageWA(
                    $phone,
                    "❌ Gambar tidak sesuai dengan menu yang dipilih.\n\n" .
                    "Alasan: " . ($validation['message'] ?? 'Data wajib tidak ditemukan.') . "\n\n" .
                    "Silakan kirim gambar yang sesuai.\n" .
                    "Ketik *ulang* untuk kembali ke menu."
                );
                return;
            }

            $extension = $this->extensionFromMime($contentType);
            $filename = 'wa_' . $phone . '_' . time() . '.' . $extension;
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
                            'whatsapp_phone' => $phone,
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
                    DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
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
                        SendSms::sendMessageWA($phone, "❌ Data token listrik gagal dideteksi, dan tidak ada data aktif untuk cabang Anda.\n\nSilakan hubungi admin.");
                        $this->resetToMenu($phone);
                        return;
                    }

                    $text = "❌ Nomor meter tidak terdeteksi otomatis dengan benar.\n\nSilakan pilih Nomor Meter cabang Anda:\n\n";
                    foreach ($tokens as $index => $token) {
                        $no = $index + 1;
                        $text .= "*{$no}*. {$token->nomor_meter} ({$token->nama_pelanggan})\n";
                    }
                    $text .= "\n*0*. Hubungi Admin\n\nKetik nomor pilihan Anda.";
                    SendSms::sendMessageWA($phone, $text);
                    return;
                }

                if (!$kwhValid) {
                    DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
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

                    SendSms::sendMessageWA(
                        $phone,
                        "ℹ️ Nomor Meter terdeteksi: *{$nomorMeter}*\n" .
                        "❌ Namun angka kWh tidak terbaca jelas.\n\n" .
                        "Silakan *ketik/input angka kWh* yang tertera pada meteran saat ini (Contoh: 125.50):"
                    );
                    return;
                }

                $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
                $analysis['data_penting']['nomor_meter'] = $nomorMeter;
                $analysis['data_penting']['kwh'] = $this->normalizeKwh($kwh);
                $analysis['data_penting']['master_token_listrik'] = ['id' => $masterToken->id, 'nomor_meter' => $masterToken->nomor_meter];

                $scan->update(['analysis_result' => $analysis]);
            }

            if (in_array($scanType, ['printer', 'cea', 'asaba'], true)) {
                $serialNumber = trim((string) ($validation['data']['serial_number'] ?? ''));

                $mesin = null;
                if ($serialNumber !== '') {
                    $mesin = MasterMesin::where('serial_number', $serialNumber)
                        ->where('is_active', true)
                        ->first();
                }

                if (!$mesin) {
                    DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                        'step' => 'ASK_MACHINE_SERIAL_CORRECTION',
                        'last_image_scan_id' => $scan->id,
                        'updated_at' => now(),
                    ]);

                    $scan->update([
                        'status' => 'failed',
                        'error_message' => 'Serial number mesin tidak ditemukan di database.',
                    ]);

                    $machines = MasterMesin::where('master_cabang_id', $session->cabang_id)
                        ->where('is_active', true)
                        ->orderBy('nama_mesin')
                        ->get();

                    if ($machines->isEmpty()) {
                        SendSms::sendMessageWA(
                            $phone,
                            "❌ Data mesin gagal dideteksi otomatis, dan tidak ditemukan data mesin aktif untuk cabang Anda.\n\nSilakan hubungi admin."
                        );
                        $this->resetToMenu($phone);
                        return;
                    }

                    $text = "❌ Serial Number mesin tidak terdeteksi otomatis dengan benar.\n\n";
                    $text .= "Silakan pilih Mesin yang sesuai untuk cabang Anda:\n\n";

                    foreach ($machines as $index => $m) {
                        $no = $index + 1;
                        $text .= "*{$no}*. {$m->nama_mesin} (SN: {$m->serial_number})\n";
                    }

                    $text .= "\n*0*. Hubungi Admin (Tidak ada di list)\n\n";
                    $text .= "Ketik nomor pilihan Anda.";

                    SendSms::sendMessageWA($phone, $text);
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
                DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                    'step' => 'ASK_CEA_TINTA',
                    'last_image_scan_id' => $scan->id,
                    'updated_at' => now(),
                ]);

                SendSms::sendMessageWA(
                    $phone,
                    "✅ Foto CEA berhasil diterima.\n" .
                    "Sedang dianalisis oleh sistem.\n\n" .
                    "ID Scan: *{$scan->id}*\n\n" .
                    "Silakan masukkan nominal *biaya tinta*.\n" .
                    "Contoh: 185000"
                );

                return;
            }

            if ($scanType === 'part_maintenance') {
                DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                    'step' => 'ASK_PART_MAINTENANCE_NOMINAL',
                    'last_image_scan_id' => $scan->id,
                    'updated_at' => now(),
                ]);

                SendSms::sendMessageWA(
                    $phone,
                    "✅ Foto bukti berhasil diterima.\n" .
                    "Foto disimpan sebagai arsip/bukti.\n\n" .
                    "ID Scan: *{$scan->id}*\n\n" .
                    "Silakan masukkan biaya part atau maintenance.\n" .
                    "Contoh: 25000"
                );

                return;
            }

            DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
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

            SendSms::sendMessageWA(
                $phone,
                "✅ Gambar berhasil diterima.\n" .
                "Sedang dianalisis oleh sistem.\n\n" .
                "ID Scan: *{$scan->id}*\n" .
                "Cabang: *" . ($namaCabang ?: 'Belum terdeteksi') . "*\n\n" .
                "Silakan cek hasilnya di website.\n\n" .
                "Ketik *ulang* untuk kembali ke menu."
            );
        } catch (\Throwable $e) {
            Log::error('WA_IMAGE_UPLOAD_ERR', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "Terjadi error saat memproses gambar.\n\n" .
                "Error: " . $e->getMessage()
            );
        }
    }

    public function startWithMachineSelection(string $phone, string $menu, string $scanType): void
    {
        $session = DB::table('dbo.wa_sessions')->where('phone', $phone)->first();

        if (!$session || !$session->cabang_id) {
            SendSms::sendMessageWA(
                $phone,
                "❌ Cabang Anda belum terdeteksi.\nSilakan hubungi admin."
            );
            return;
        }

        $mesins = MasterMesin::where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->orderBy('nama_mesin')
            ->get();

        if ($mesins->isEmpty()) {
            SendSms::sendMessageWA(
                $phone,
                "❌ Belum ada mesin aktif untuk cabang Anda."
            );
            return;
        }

        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'menu' => $menu,
            'scan_type' => $scanType,
            'step' => 'ASK_MACHINE',
            'master_mesin_id' => null,
            'master_mesin_part_id' => null,
            'updated_at' => now(),
        ]);

        $text = "Menu *{$menu}* dipilih ✅\n\n";
        $text .= "Silakan pilih mesin:\n\n";

        foreach ($mesins as $index => $mesin) {
            $no = $index + 1;
            $text .= "*{$no}* {$mesin->nama_mesin}\n";
            $text .= "SN: {$mesin->serial_number}\n\n";
        }

        $text .= "Pilih nomor.";

        SendSms::sendMessageWA($phone, $text);
    }

    private function downloadWhatsappImage(array $payload): ?array
    {
        $imageUrl =
            $payload['url']
            ?? $payload['image']
            ?? $payload['media_url']
            ?? $payload['media']
            ?? $payload['file']
            ?? null;

        if (!$imageUrl) {
            return null;
        }

        $response = Http::timeout(60)->get($imageUrl);

        if ($response->failed()) {
            return null;
        }

        $mimeType = $response->header('Content-Type') ?: 'image/jpeg';

        if (!str_starts_with($mimeType, 'image/')) {
            $mimeType = 'image/jpeg';
        }

        return [
            'body' => $response->body(),
            'mime_type' => $mimeType,
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

    private function handleMachineSelection(string $phone, string $message, object $session): void
    {
        $choice = (int) trim($message);

        if ($choice <= 0) {
            SendSms::sendMessageWA($phone, "Silakan ketik nomor mesin yang valid.");
            return;
        }

        $mesins = MasterMesin::where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->orderBy('nama_mesin')
            ->get();

        $mesin = $mesins->get($choice - 1);

        if (!$mesin) {
            SendSms::sendMessageWA($phone, "Nomor mesin tidak ditemukan. Silakan pilih ulang.");
            return;
        }

        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'master_mesin_id' => $mesin->id,
            'updated_at' => now(),
        ]);

        if ($session->menu === 'BIAYA_PART') {
            $parts = $mesin->maintenanceParts()
                ->orderBy('nama_part')
                ->get();

            if ($parts->isEmpty()) {
                SendSms::sendMessageWA(
                    $phone,
                    "❌ Mesin *{$mesin->nama_mesin}* belum memiliki data part."
                );
                return;
            }

            DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                'step' => 'ASK_PART',
                'updated_at' => now(),
            ]);

            $text = "Mesin dipilih ✅\n";
            $text .= "*{$mesin->nama_mesin}*\n";
            $text .= "SN: {$mesin->serial_number}\n\n";
            $text .= "Silakan pilih part:\n\n";

            foreach ($parts as $index => $part) {
                $no = $index + 1;
                $text .= "*{$no}* {$part->nama_part}\n";
            }

            $text .= "\nKetik nomor part.";

            SendSms::sendMessageWA($phone, $text);
            return;
        }

        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'step' => 'ASK_IMAGE',
            'master_mesin_part_id' => null,
            'updated_at' => now(),
        ]);

        SendSms::sendMessageWA(
            $phone,
            "Mesin dipilih ✅\n\n" .
            "*{$mesin->nama_mesin}*\n" .
            "SN: {$mesin->serial_number}\n\n" .
            "Silakan kirim gambar/foto bukti maintenance mesin."
        );
    }

    private function handlePartSelection(string $phone, string $message, object $session): void
    {
        $choice = (int) trim($message);

        if ($choice <= 0) {
            SendSms::sendMessageWA($phone, "Silakan ketik nomor part yang valid.");
            return;
        }

        $mesin = MasterMesin::with('maintenanceParts')
            ->where('id', $session->master_mesin_id)
            ->where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->first();

        if (!$mesin) {
            SendSms::sendMessageWA($phone, "❌ Mesin tidak valid. Ketik *ulang* untuk kembali ke menu.");
            return;
        }

        $parts = $mesin->maintenanceParts()
            ->orderBy('nama_part')
            ->get();

        $part = $parts->get($choice - 1);

        if (!$part) {
            SendSms::sendMessageWA($phone, "Nomor part tidak ditemukan. Silakan pilih ulang.");
            return;
        }

        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
            'master_mesin_part_id' => $part->id,
            'step' => 'ASK_IMAGE',
            'updated_at' => now(),
        ]);

        SendSms::sendMessageWA(
            $phone,
            "Part dipilih ✅\n\n" .
            "Mesin: *{$mesin->nama_mesin}*\n" .
            "SN: {$mesin->serial_number}\n" .
            "Part: *{$part->nama_part}*\n\n" .
            "Silakan kirim gambar/foto bukti biaya part."
        );
    }

    private function handleCeaTinta(string $phone, string $message, object $session): void
    {
        $biayaTinta = $this->extractNominalFromMessage($message);

        if ($biayaTinta === null) {
            SendSms::sendMessageWA(
                $phone,
                "Nominal tinta tidak valid.\n\nContoh: 185000"
            );
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendSms::sendMessageWA(
                $phone,
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

        $this->resetToMenu($phone);

        SendSms::sendMessageWA(
            $phone,
            "✅ Biaya tinta CEA berhasil disimpan.\n\n" .
            "Tinta: Rp " . number_format($biayaTinta, 0, ',', '.') . "\n" .
            "Periode: " . $periodeStart->format('d/m/Y') . " - " . $periodeEnd->format('d/m/Y') . "\n\n" .
            "Ketik *ulang* untuk kembali ke menu."
        );
    }

    private function handlePartMaintenanceNominal(string $phone, string $message, object $session): void
    {
        $nominal = $this->extractNominalFromMessage($message);

        if ($nominal === null) {
            SendSms::sendMessageWA(
                $phone,
                "Nominal tidak valid.\n\nContoh: 250000"
            );
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendSms::sendMessageWA(
                $phone,
                "Data foto terakhir tidak ditemukan. Silakan ulangi upload foto."
            );
            return;
        }

        $mesin = MasterMesin::find($scan->master_mesin_id);

        if (!$mesin) {
            SendSms::sendMessageWA($phone, "Data mesin tidak ditemukan.");
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

        $this->resetToMenu($phone);

        SendSms::sendMessageWA(
            $phone,
            "✅ Biaya berhasil disimpan.\n\n" .
            "Jenis: *" . ($isPart ? "Biaya Part" : "Biaya Maintenance") . "*\n" .
            "Nominal: Rp " . number_format($nominal, 0, ',', '.') . "\n" .
            "Periode: " . $periodeStart->format('d/m/Y') . " - " . $periodeEnd->format('d/m/Y') . "\n\n" .
            "Ketik *ulang* untuk kembali ke menu."
        );
    }

    private function handleElectricityCorrection(string $phone, string $message, object $session): void
    {
        $choice = trim($message);

        if ($choice === '0') {
            SendSms::sendMessageWA($phone, "Silakan hubungi admin.\n\nKetik *ulang* untuk kembali.");
            $this->resetToMenu($phone);
            return;
        }

        $choiceIndex = (int) $choice;
        $tokens = MasterTokenListrik::where('master_cabang_id', $session->cabang_id)->where('is_active', true)->orderBy('nomor_meter')->get();
        $selectedToken = $tokens->get($choiceIndex - 1);

        if (!$selectedToken) {
            SendSms::sendMessageWA($phone, "❌ Pilihan tidak valid.");
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
            DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
                'step' => 'ASK_KWH_CORRECTION',
                'updated_at' => now(),
            ]);

            SendSms::sendMessageWA(
                $phone,
                "✅ Nomor Meter dipilih: *{$selectedToken->nomor_meter}*\n\n" .
                "📝 Langkah terakhir, angka kWh tidak terbaca jelas. Silakan *ketik langsung nilai kWh saat ini* (Contoh: 250.75):"
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
        $this->resetToMenu($phone);
        SendSms::sendMessageWA($phone, "✅ Data berhasil disimpan.\nMeter: *{$selectedToken->nomor_meter}*\nkWh: *{$dataPenting['kwh']}*");
    }

    private function handleKwhCorrection(string $phone, string $message, object $session): void
    {
        $inputKwh = trim($message);

        $inputKwh = str_replace(',', '.', $inputKwh);
        $inputKwh = preg_replace('/[^0-9.]/', '', $inputKwh);

        if ($inputKwh === '' || !is_numeric($inputKwh) || (float)$inputKwh < 0) {
            SendSms::sendMessageWA($phone, "⚠️ Nilai kWh tidak valid. Silakan masukkan angka yang benar (Contoh: 239.85):");
            return;
        }

        if (strpos($inputKwh, '.') === false) {
            $length = strlen($inputKwh);

            if ($length > 2) {
                $angkaDepan = substr($inputKwh, 0, $length - 2);
                $angkaDesimal = substr($inputKwh, -2);
                $inputKwh = $angkaDepan . '.' . $angkaDesimal;
            } else {
                $inputKwh = '0.' . str_pad($inputKwh, 2, '0', STR_PAD_LEFT);
            }
        }

        $scan = ImageScan::find($session->last_image_scan_id);
        $analysis = is_array($scan->analysis_result) ? $scan->analysis_result : [];
        $dataPenting = $analysis['data_penting'] ?? [];

        $formattedKwh = number_format((float)$inputKwh, 2, '.', '');

        $dataPenting['kwh'] = $formattedKwh;
        $dataPenting['is_manual_correction'] = true;
        $dataPenting['kwh_corrected_at'] = now()->toDateTimeString();

        $analysis['data_penting'] = $dataPenting;

        $scan->update([
            'analysis_result' => $analysis,
            'status' => 'success',
            'error_message' => null,
        ]);

        AnalyzeImageJob::dispatch($scan->id);

        $this->resetToMenu($phone);

        SendSms::sendMessageWA(
            $phone,
            "✅ Data kWh berhasil dimasukkan dan diformat otomatis.\n\n" .
            "Nomor Meter: *" . ($dataPenting['nomor_meter'] ?? '-') . "*\n" .
            "Nilai kWh Terformat: *{$formattedKwh}*\n\n" .
            "Ketik *ulang* untuk kembali."
        );
    }

    private function handleMachineSerialCorrection(string $phone, string $message, object $session): void
    {
        $choice = trim($message);

        if ($choice === '0') {
            SendSms::sendMessageWA(
                $phone,
                "Silakan hubungi admin untuk mendaftarkan atau memperbaiki data Serial Number mesin Anda.\n\nKetik *ulang* untuk kembali."
            );
            $this->resetToMenu($phone);
            return;
        }

        $choiceIndex = (int) $choice;
        if ($choiceIndex <= 0) {
            SendSms::sendMessageWA($phone, "⚠️ Pilihan tidak valid. Silakan ketik nomor urut yang sesuai atau 0.");
            return;
        }

        $machines = MasterMesin::where('master_cabang_id', $session->cabang_id)
            ->where('is_active', true)
            ->orderBy('nama_mesin')
            ->get();

        $selectedMachine = $machines->get($choiceIndex - 1);

        if (!$selectedMachine) {
            SendSms::sendMessageWA($phone, "❌ Nomor pilihan tidak ditemukan. Silakan pilih nomor yang tertera pada daftar.");
            return;
        }

        $scan = ImageScan::find($session->last_image_scan_id);

        if (!$scan) {
            SendSms::sendMessageWA($phone, "Data scan terakhir tidak ditemukan. Silakan ulangi upload foto.");
            $this->resetToMenu($phone);
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

        $this->resetToMenu($phone);

        SendSms::sendMessageWA(
            $phone,
            "✅ Data mesin berhasil dikoreksi dan disimpan.\n\n" .
            "Nama Mesin: *{$selectedMachine->nama_mesin}*\n" .
            "Serial Number: *{$selectedMachine->serial_number}*\n\n" .
            "Ketik *ulang* untuk kembali ke menu."
        );
    }

    private function resetToMenu(string $phone): void
    {
        DB::table('dbo.wa_sessions')->where('phone', $phone)->update([
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
