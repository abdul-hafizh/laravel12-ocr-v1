<?php

namespace App\Services\ManualUpload;

use App\Models\MasterMesin;
use App\Models\MasterTokenListrik;
use App\Services\ImageAnalysisPromptService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ManualUploadService
{
    public function validateImage(string $scanType, string $imageBody, string $mimeType): array
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
            isset($parsed['data_penting']['nominal']) &&
            !is_numeric($parsed['data_penting']['nominal'])
        ) {
            $parsed['data_penting']['nominal'] = (int) preg_replace(
                '/[^0-9]/',
                '',
                (string) $parsed['data_penting']['nominal']
            );
        }

        if (!is_array($parsed)) {
            return [
                'valid' => false,
                'message' => 'Sistem tidak dapat membaca isi gambar dengan jelas.',
            ];
        }

        return [
            'valid' => (bool) ($parsed['valid'] ?? false),
            'message' => $parsed['message'] ?? 'Data wajib tidak ditemukan.',
            'data' => $parsed['data_penting'] ?? [],
        ];
    }

    public function isValidKwh($kwh): bool
    {
        $kwh = trim((string) $kwh);
        $kwh = str_replace(',', '.', $kwh);

        return preg_match('/^\d+\.\d{2}$/', $kwh) === 1;
    }

    public function normalizeKwh($kwh): string
    {
        $kwh = trim((string) $kwh);
        $kwh = str_replace(',', '.', $kwh);

        return number_format((float) $kwh, 2, '.', '');
    }

    public function resolveElectricityMatch(array $data): array
    {
        $nomorMeter = preg_replace('/[^0-9]/', '', (string) ($data['nomor_meter'] ?? ''));
        $kwh = $data['kwh'] ?? null;
        $kwhValid = $kwh && $this->isValidKwh($kwh);

        $masterToken = null;
        if ($nomorMeter) {
            $masterToken = MasterTokenListrik::where('nomor_meter', $nomorMeter)
                ->where('is_active', true)
                ->first();
        }

        return [
            'nomor_meter' => $nomorMeter,
            'kwh' => $kwh,
            'kwh_valid' => $kwhValid,
            'master_token' => $masterToken,
        ];
    }

    public function resolveMachineMatch(array $data): array
    {
        $serialNumber = trim((string) ($data['serial_number'] ?? ''));

        $mesin = null;
        if ($serialNumber !== '') {
            $mesin = MasterMesin::where('serial_number', $serialNumber)
                ->where('is_active', true)
                ->first();
        }

        return [
            'serial_number' => $serialNumber,
            'master_mesin' => $mesin,
        ];
    }

    public function tokenCandidates(int $cabangId): Collection
    {
        return MasterTokenListrik::where('master_cabang_id', $cabangId)
            ->where('is_active', true)
            ->orderBy('nomor_meter')
            ->get();
    }

    public function machineCandidates(int $cabangId): Collection
    {
        return MasterMesin::where('master_cabang_id', $cabangId)
            ->where('is_active', true)
            ->orderBy('nama_mesin')
            ->get();
    }

    public function printerPerhitungan(array $data, MasterMesin $mesin): array
    {
        $bw = (int) ($data['total_bw'] ?? 0);
        $color = (int) ($data['total_color'] ?? 0);
        $longSheet = (int) ($data['total_long_sheet'] ?? 0);

        return [
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
