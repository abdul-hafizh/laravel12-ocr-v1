<?php

namespace App\Http\Controllers;

use App\Exports\ElectricitySummaryExport;
use App\Libraries\SendSms;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class SummaryController extends Controller
{
    public function electricity(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $search = $request->input('search');
        $cabangId = $request->input('cabang_id');

        $summary = $this->getElectricitySummaryData($month, $search, $cabangId);

        $cabangs = DB::table('v_image_scan_electricity')
            ->select('cabang_id', 'nama_cabang', 'kode_cabang')
            ->where('scan_type', 'electricity')
            ->whereNotNull('cabang_id')
            ->groupBy('cabang_id', 'nama_cabang', 'kode_cabang')
            ->orderBy('nama_cabang')
            ->get();

        return Inertia::render('Summary/Electricity', [
            'summary' => $summary,
            'cabangs' => $cabangs,
            'filters' => [
                'month' => $month,
                'search' => $search,
                'cabang_id' => $cabangId,
            ],
        ]);
    }

    public function sendElectricityWa(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $search = $request->input('search');
        $cabangId = $request->input('cabang_id');

        $summary = $this->getElectricitySummaryData($month, $search, $cabangId);

        if (count($summary) === 0) {
            return back()->with('error', 'Tidak ada data summary untuk dikirim.');
        }

        $fileName = 'summary-token-listrik-' . $month . '-' . now()->format('His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        $excelFile = Excel::raw(
            new ElectricitySummaryExport($summary),
            ExcelFormat::XLSX
        );

        Storage::disk('public')->put($filePath, $excelFile);

        $fileUrl = asset('storage/' . $filePath);

        $financeUsers = User::where('is_active', true)
            ->where('is_delete', false)
            ->whereNotNull('phone')
            ->whereHas('role', function ($q) {
                $q->where('slug', 'finance');
            })
            ->get();

        if ($financeUsers->isEmpty()) {
            return back()->with('error', 'Tidak ada user finance yang memiliki nomor WhatsApp.');
        }

        foreach ($financeUsers as $user) {
            SendSms::sendDocumentWA(
                $user->phone,
                $fileUrl,
                "Summary Token Listrik periode {$month}"
            );
        }

        return back()->with('success', 'File Excel summary berhasil dikirim ke semua finance.');
    }

    private function getElectricitySummaryData($month, $search = null, $cabangId = null): array
    {
        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = Carbon::parse($month . '-01')->endOfMonth();

        $query = DB::table('v_image_scan_electricity')
            ->where('scan_type', 'electricity')
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($cabangId)) {
            $query->where('cabang_id', $cabangId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_cabang', 'like', "%{$search}%")
                    ->orWhere('kode_cabang', 'like', "%{$search}%")
                    ->orWhere('nomor_meter', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('nama_pelanggan', 'like', "%{$search}%")
                    ->orWhere('user_phone', 'like', "%{$search}%");
            });
        }

        $rows = $query
            ->orderBy('cabang_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy(function ($item) {
                return $item->cabang_id . '-' . ($item->master_token_listrik_id ?? 'no-master');
            });

        $summary = [];

        foreach ($rows as $groupKey => $items) {
            $awal = $items->first();
            $akhir = $items->last();

            $kwhAwal = $this->toFloat($awal->kwh ?? 0);
            $kwhAkhir = $this->toFloat($akhir->kwh ?? 0);

            $pemakaianKwh = max($kwhAwal - $kwhAkhir, 0);

            $hargaPerKwh = $this->toFloat($awal->harga_per_kwh ?? 0);

            $estimasiPemakaianRupiah = $pemakaianKwh * $hargaPerKwh;
            $estimasiSisaRupiah = $kwhAkhir * $hargaPerKwh;

            $summary[] = [
                'cabang_id' => $awal->cabang_id,
                'nama_cabang' => $awal->nama_cabang,
                'kode_cabang' => $awal->kode_cabang,

                'master_token_listrik_id' => $awal->master_token_listrik_id,
                'nama_pelanggan' => $awal->nama_pelanggan,
                'daya' => $awal->daya,
                'nomor_meter' => $awal->nomor_meter,
                'barcode' => $awal->barcode,
                'status_master_token' => $awal->status_master_token,

                'periode' => $month,

                'tanggal_awal' => $awal->created_at,
                'tanggal_akhir' => $akhir->created_at,

                'kwh_awal' => round($kwhAwal, 2),
                'kwh_akhir' => round($kwhAkhir, 2),
                'pemakaian_kwh' => round($pemakaianKwh, 2),

                'harga_per_kwh' => round($hargaPerKwh, 2),
                'estimasi_harga_per_kwh' => round($hargaPerKwh, 2),
                'estimasi_pemakaian_rupiah' => round($estimasiPemakaianRupiah, 2),
                'estimasi_sisa_rupiah' => round($estimasiSisaRupiah, 2),

                'rekomendasi_topup_bulan_depan' => round(
                    max($estimasiPemakaianRupiah - $estimasiSisaRupiah, 0),
                    2
                ),

                'jumlah_foto' => $items->count(),
                'status_summary' => $this->getStatusSummary(
                    $items,
                    $kwhAwal,
                    $kwhAkhir,
                    $hargaPerKwh,
                    $awal->master_token_listrik_id,
                    $awal->token_is_active
                ),
            ];
        }

        return $summary;
    }

    private function getStatusSummary(
        $items,
        float $kwhAwal,
        float $kwhAkhir,
        float $hargaPerKwh,
        $masterTokenId = null,
        $tokenIsActive = null
    ): string {
        if (empty($masterTokenId)) {
            return 'Master tidak ditemukan';
        }

        if ((int) $tokenIsActive === 0) {
            return 'Master tidak aktif';
        }

        if ($items->count() < 2) {
            return 'Belum lengkap';
        }

        if ($hargaPerKwh <= 0) {
            return 'Harga/kWh belum diisi';
        }

        if ($kwhAkhir > $kwhAwal) {
            return 'Perlu dicek';
        }

        return 'Lengkap';
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = (string) $value;
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }
}