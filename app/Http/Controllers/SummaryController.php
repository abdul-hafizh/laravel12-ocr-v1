<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SummaryController extends Controller
{
    public function electricity(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $search = $request->input('search');
        $cabangId = $request->input('cabang_id');

        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = Carbon::parse($month . '-01')->endOfMonth();

        $query = DB::table('v_image_scan_results')
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
                    ->orWhere('user_phone', 'like', "%{$search}%");
            });
        }

        $rows = $query
            ->orderBy('cabang_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('cabang_id');

        $summary = [];

        foreach ($rows as $groupCabangId => $items) {
            $awal = $items->first();
            $akhir = $items->last();

            $kwhAwal = $this->toFloat($awal->kwh ?? 0);
            $kwhAkhir = $this->toFloat($akhir->kwh ?? 0);

            $pemakaianKwh = max($kwhAwal - $kwhAkhir, 0);

            $masterToken = DB::table('master_token_listriks')
                ->where('master_cabang_id', $groupCabangId)
                ->where('is_active', 1)
                ->first();

            $nominalDefault = $this->toFloat($masterToken->nominal_default ?? 0);

            $estimasiPemakaianRupiah = $pemakaianKwh * $nominalDefault;
            $estimasiSisaRupiah = $kwhAkhir * $nominalDefault;

            $summary[] = [
                'cabang_id' => $groupCabangId,
                'nama_cabang' => $awal->nama_cabang,
                'kode_cabang' => $awal->kode_cabang,
                'nomor_meter' => $awal->nomor_meter,

                'periode' => $month,

                'tanggal_awal' => $awal->created_at,
                'tanggal_akhir' => $akhir->created_at,

                'kwh_awal' => round($kwhAwal, 2),
                'kwh_akhir' => round($kwhAkhir, 2),
                'pemakaian_kwh' => round($pemakaianKwh, 2),

                'nominal_default' => round($nominalDefault, 2),
                'estimasi_harga_per_kwh' => round($nominalDefault, 2),
                'estimasi_pemakaian_rupiah' => round($estimasiPemakaianRupiah, 2),
                'estimasi_sisa_rupiah' => round($estimasiSisaRupiah, 2),

                'rekomendasi_topup_bulan_depan' => round($estimasiPemakaianRupiah, 2),

                'jumlah_foto' => $items->count(),
                'status_summary' => $this->getStatusSummary($items, $kwhAwal, $kwhAkhir, $nominalDefault),
            ];
        }

        $cabangs = DB::table('v_image_scan_results')
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

    private function getStatusSummary($items, float $kwhAwal, float $kwhAkhir, float $hargaPerKwh): string
    {
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