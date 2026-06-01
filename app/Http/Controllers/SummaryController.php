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

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            $endDateCarbon = Carbon::parse($month . '-28')->endOfDay();
            $startDateCarbon = Carbon::parse($month . '-28')->subMonthNoOverflow()->startOfDay();

            $startDate = $startDateCarbon->toDateString();
            $endDate = $endDateCarbon->toDateString();
        }

        $search = $request->input('search');
        $cabangId = $request->input('cabang_id');

        $summary = $this->getElectricitySummaryData($startDate, $endDate, $search, $cabangId);

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
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
                'cabang_id' => $cabangId,
            ],
        ]);
    }

    public function printerBilling(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            $endDateCarbon = Carbon::parse($month . '-28')->endOfDay();
            $startDateCarbon = Carbon::parse($month . '-28')
                ->subMonthNoOverflow()
                ->startOfDay();

            $startDate = $startDateCarbon->toDateString();
            $endDate = $endDateCarbon->toDateString();
        }

        $query = DB::table('dbo.v_image_scan_printers as p')
            ->leftJoin('dbo.master_mesins as mm', 'mm.id', '=', 'p.master_mesin_id')
            ->select([
                'p.*',

                'mm.harga_color_a3',
                'mm.harga_color_a4',
                'mm.harga_bw_a3',
                'mm.harga_bw_a4',

                'mm.minimum_charge_click',
                'mm.minimum_charge_size',
                'mm.minimum_charge_nominal',

                'mm.over_click_color_a3',
                'mm.over_click_color_a4',
                'mm.over_click_bw_a3',
                'mm.over_click_bw_a4',

                'mm.free_klik_percent',
                'mm.keterangan as master_keterangan',
            ])
            ->whereBetween('p.created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('p.serial_number', 'like', "%{$search}%")
                    ->orWhere('p.nama_mesin', 'like', "%{$search}%")
                    ->orWhere('p.master_nama_mesin', 'like', "%{$search}%")
                    ->orWhere('p.nama_vendor', 'like', "%{$search}%")
                    ->orWhere('p.kode_vendor', 'like', "%{$search}%")
                    ->orWhere('p.nama_cabang', 'like', "%{$search}%")
                    ->orWhere('p.kode_cabang', 'like', "%{$search}%");
            });
        }

        if ($request->filled('vendor')) {
            $query->where('p.master_vendor_id', $request->vendor);
        }

        if ($request->filled('cabang_id')) {
            $query->where('p.cabang_id', $request->cabang_id);
        }

        $billings = $query
            ->orderByDesc('p.created_at')
            ->paginate(10)
            ->withQueryString();

        $billings->getCollection()->transform(function ($item) {
            $bwA3 = (int) ($item->bw_a3 ?? 0);
            $bwA4 = (int) ($item->bw_a4 ?? 0);
            $colorA3 = (int) ($item->color_a3 ?? 0);
            $colorA4 = (int) ($item->color_a4 ?? 0);

            $bwLongSheet = (int) ($item->bw_long_sheet ?? 0);
            $colorLongSheet = (int) ($item->color_long_sheet ?? 0);

            $hargaBwA3 = (float) ($item->harga_bw_a3 ?? 0);
            $hargaBwA4 = (float) ($item->harga_bw_a4 ?? 0);
            $hargaColorA3 = (float) ($item->harga_color_a3 ?? 0);
            $hargaColorA4 = (float) ($item->harga_color_a4 ?? 0);

            $minimumClick = (int) ($item->minimum_charge_click ?? 0);
            $minimumSize = $item->minimum_charge_size ?? null;
            $minimumNominal = (float) ($item->minimum_charge_nominal ?? 0);
            $freePercent = (float) ($item->free_klik_percent ?? 0);

            $biayaBwA3 = $bwA3 * $hargaBwA3;
            $biayaBwA4 = $bwA4 * $hargaBwA4;
            $biayaColorA3 = $colorA3 * $hargaColorA3;
            $biayaColorA4 = $colorA4 * $hargaColorA4;

            $biayaBwLongSheet = $bwLongSheet * $hargaBwA3;
            $biayaColorLongSheet = $colorLongSheet * $hargaColorA3;

            $subtotal = $biayaBwA3
                + $biayaBwA4
                + $biayaColorA3
                + $biayaColorA4
                + $biayaBwLongSheet
                + $biayaColorLongSheet;

            if ($minimumSize === 'A3') {
                $minimumBasisClick = $bwA3 + $colorA3 + $bwLongSheet + $colorLongSheet;
            } elseif ($minimumSize === 'A4') {
                $minimumBasisClick = $bwA4 + $colorA4;
            } else {
                $minimumBasisClick = $bwA3 + $bwA4 + $colorA3 + $colorA4 + $bwLongSheet + $colorLongSheet;
            }

            $freeKlik = 0;
            if ($freePercent > 0) {
                $freeKlik = floor($minimumBasisClick * ($freePercent / 100));
            }

            $subtotalSetelahFree = $subtotal;

            if ($freeKlik > 0 && $minimumBasisClick > 0) {
                $nilaiPerKlikRataRata = $subtotal / $minimumBasisClick;
                $subtotalSetelahFree = max(0, $subtotal - ($freeKlik * $nilaiPerKlikRataRata));
            }

            $totalTagihan = $subtotalSetelahFree;

            if ($minimumClick > 0 && $minimumNominal > 0 && $minimumBasisClick < $minimumClick) {
                $totalTagihan = $minimumNominal;
            }

            $item->billing_detail = [
                'biaya_bw_a3' => $biayaBwA3,
                'biaya_bw_a4' => $biayaBwA4,
                'biaya_color_a3' => $biayaColorA3,
                'biaya_color_a4' => $biayaColorA4,
                'biaya_bw_long_sheet' => $biayaBwLongSheet,
                'biaya_color_long_sheet' => $biayaColorLongSheet,
                'subtotal' => $subtotal,
                'minimum_basis_click' => $minimumBasisClick,
                'minimum_charge_click' => $minimumClick,
                'minimum_charge_size' => $minimumSize,
                'minimum_charge_nominal' => $minimumNominal,
                'free_klik' => $freeKlik,
                'subtotal_setelah_free' => $subtotalSetelahFree,
                'total_tagihan' => $totalTagihan,
            ];

            $item->total_tagihan = $totalTagihan;

            return $item;
        });

        if ($request->boolean('debug')) {
            dd($billings->items());
        }

        return Inertia::render('Summary/Printer', [
            'billings' => $billings,

            'vendors' => DB::table('dbo.master_vendors')
                ->where('is_active', true)
                ->orderBy('nama_vendor')
                ->get([
                    'id',
                    'kode_vendor',
                    'nama_vendor',
                ]),

            'cabangs' => DB::table('dbo.master_cabangs')
                ->where('is_active', true)
                ->orderBy('nama_cabang')
                ->get([
                    'id',
                    'kode_cabang',
                    'nama_cabang',
                ]),

            'filters' => [
                'search' => $request->input('search'),
                'vendor' => $request->input('vendor'),
                'billing_status' => $request->input('billing_status'),
                'cabang_id' => $request->input('cabang_id'),
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function sendElectricityWa(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            $endDateCarbon = Carbon::parse($month . '-28')->endOfDay();
            $startDateCarbon = Carbon::parse($month . '-28')->subMonthNoOverflow()->startOfDay();

            $startDate = $startDateCarbon->toDateString();
            $endDate = $endDateCarbon->toDateString();
        }

        $search = $request->input('search');
        $cabangId = $request->input('cabang_id');

        $summary = $this->getElectricitySummaryData($startDate, $endDate, $search, $cabangId);

        if (count($summary) === 0) {
            return back()->with('error', 'Tidak ada data summary untuk dikirim.');
        }

        $periodeLabel = Carbon::parse($startDate)->format('d-m-Y') . '_sd_' . Carbon::parse($endDate)->format('d-m-Y');

        $fileName = 'summary-token-listrik-' . $periodeLabel . '-' . now()->format('His') . '.xlsx';
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
                "Summary Token Listrik periode {$periodeLabel}"
            );
        }

        return back()->with('success', 'File Excel summary berhasil dikirim ke semua finance.');
    }

    private function getElectricitySummaryData($startDate, $endDate, $search = null, $cabangId = null): array
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        $periode = $startDate->format('d-m-Y') . ' s/d ' . $endDate->format('d-m-Y');

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

                'periode' => $periode,

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