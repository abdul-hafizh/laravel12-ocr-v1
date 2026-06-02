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

        $periodeStart = Carbon::parse($startDate)->startOfDay();
        $periodeEnd = Carbon::parse($endDate)->endOfDay();

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
                $periodeStart,
                $periodeEnd,
            ])
            ->whereRaw("
                p.created_at = (
                    SELECT MAX(p2.created_at)
                    FROM dbo.v_image_scan_printers p2
                    WHERE p2.serial_number = p.serial_number
                    AND p2.created_at BETWEEN ? AND ?
                )
            ", [
                $periodeStart,
                $periodeEnd,
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

        $billings->getCollection()->transform(function ($item) use ($periodeStart) {
            $previousScan = DB::table('dbo.v_image_scan_printers')
                ->where('serial_number', $item->serial_number)
                ->where('created_at', '<=', $periodeStart)
                ->orderByDesc('created_at')
                ->first();

            $getCounter = function ($value) {
                return (int) preg_replace('/[^0-9]/', '', (string) ($value ?? 0));
            };

            $currentBwA3 = $getCounter($item->bw_a3 ?? 0);
            $currentBwA4 = $getCounter($item->bw_a4 ?? 0);
            $currentColorA3 = $getCounter($item->color_a3 ?? 0);
            $currentColorA4 = $getCounter($item->color_a4 ?? 0);
            $currentBwLongSheet = $getCounter($item->bw_long_sheet ?? 0);
            $currentColorLongSheet = $getCounter($item->color_long_sheet ?? 0);

            $previousBwA3 = $getCounter($previousScan->bw_a3 ?? 0);
            $previousBwA4 = $getCounter($previousScan->bw_a4 ?? 0);
            $previousColorA3 = $getCounter($previousScan->color_a3 ?? 0);
            $previousColorA4 = $getCounter($previousScan->color_a4 ?? 0);
            $previousBwLongSheet = $getCounter($previousScan->bw_long_sheet ?? 0);
            $previousColorLongSheet = $getCounter($previousScan->color_long_sheet ?? 0);

            if (!$previousScan) {
                $bwA3 = 0;
                $bwA4 = 0;
                $colorA3 = 0;
                $colorA4 = 0;
                $bwLongSheet = 0;
                $colorLongSheet = 0;
            } else {
                $bwA3 = max(0, $currentBwA3 - $previousBwA3);
                $bwA4 = max(0, $currentBwA4 - $previousBwA4);
                $colorA3 = max(0, $currentColorA3 - $previousColorA3);
                $colorA4 = max(0, $currentColorA4 - $previousColorA4);
                $bwLongSheet = max(0, $currentBwLongSheet - $previousBwLongSheet);
                $colorLongSheet = max(0, $currentColorLongSheet - $previousColorLongSheet);
            }

            $hargaColorA3 = (float) ($item->harga_color_a3 ?? 0);
            $hargaColorA4 = (float) ($item->harga_color_a4 ?? 0);
            $hargaBwA3 = (float) ($item->harga_bw_a3 ?? 0);
            $hargaBwA4 = (float) ($item->harga_bw_a4 ?? 0);

            $minimumSize = strtoupper((string) ($item->minimum_charge_size ?? ''));
            $minimumClick = (int) ($item->minimum_charge_click ?? 0);
            $minimumNominal = (float) ($item->minimum_charge_nominal ?? 0);
            $freePercent = (float) ($item->free_klik_percent ?? 0);

            $overColorA3 = (float) ($item->over_click_color_a3 ?? 0);
            $overColorA4 = (float) ($item->over_click_color_a4 ?? 0);
            $overBwA3 = (float) ($item->over_click_bw_a3 ?? 0);
            $overBwA4 = (float) ($item->over_click_bw_a4 ?? 0);

            $rateColorA3 = $overColorA3 > 0 ? $overColorA3 : $hargaColorA3;
            $rateColorA4 = $overColorA4 > 0 ? $overColorA4 : $hargaColorA4;
            $rateBwA3 = $overBwA3 > 0 ? $overBwA3 : $hargaBwA3;
            $rateBwA4 = $overBwA4 > 0 ? $overBwA4 : $hargaBwA4;

            $totalA3Click = $bwA3 + $colorA3 + $bwLongSheet + $colorLongSheet;
            $totalA4Click = $bwA4 + $colorA4;

            $totalPemakaianClick =
                $bwA3
                + $bwA4
                + $colorA3
                + $colorA4
                + $bwLongSheet
                + $colorLongSheet;

            if ($minimumSize === 'A3') {
                $minimumBasisClick = $totalA3Click;
            } elseif ($minimumSize === 'A4') {
                $minimumBasisClick = $totalA4Click;
            } else {
                $minimumBasisClick = 0;
            }

            $hasMinimumRule =
                in_array($minimumSize, ['A3', 'A4'])
                && $minimumClick > 0
                && $minimumNominal > 0;

            $biayaBwA3 = $bwA3 * $rateBwA3;
            $biayaBwA4 = $bwA4 * $rateBwA4;
            $biayaColorA3 = $colorA3 * $rateColorA3;
            $biayaColorA4 = $colorA4 * $rateColorA4;
            $biayaBwLongSheet = $bwLongSheet * $rateBwA3;
            $biayaColorLongSheet = $colorLongSheet * $rateColorA3;

            $subtotalBilling =
                $biayaBwA3
                + $biayaBwA4
                + $biayaColorA3
                + $biayaColorA4
                + $biayaBwLongSheet
                + $biayaColorLongSheet;

            $subtotalSebelumFree = $subtotalBilling;

            $freeKlik = 0;
            $nilaiFreeKlik = 0;

            if ($freePercent > 0 && $totalPemakaianClick > 0 && !$hasMinimumRule) {
                $freeKlik = floor($totalPemakaianClick * ($freePercent / 100));

                if ($subtotalBilling > 0) {
                    $nilaiPerKlikRataRata = $subtotalBilling / $totalPemakaianClick;
                    $nilaiFreeKlik = $freeKlik * $nilaiPerKlikRataRata;
                }
            }

            $subtotalSetelahFree = max(0, $subtotalBilling - $nilaiFreeKlik);

            if (!$previousScan || $totalPemakaianClick <= 0) {
                $totalTagihan = 0;
                $billingRule = 'no_previous_scan';
            } elseif ($hasMinimumRule && $minimumBasisClick < $minimumClick) {
                $totalTagihan = $minimumNominal;
                $billingRule = 'minimum_charge_' . strtolower($minimumSize);
            } else {
                $totalTagihan = $subtotalSetelahFree;

                if ($hasMinimumRule && $minimumBasisClick >= $minimumClick) {
                    $billingRule = 'over_minimum_' . strtolower($minimumSize);
                } else {
                    $billingRule = 'standard';
                }
            }

            $item->counter_detail = [
                'has_previous_scan' => $previousScan ? true : false,
                'previous_created_at' => $previousScan->created_at ?? null,
                'current_created_at' => $item->created_at,

                'current_bw_a3' => $currentBwA3,
                'previous_bw_a3' => $previousBwA3,
                'usage_bw_a3' => $bwA3,

                'current_bw_a4' => $currentBwA4,
                'previous_bw_a4' => $previousBwA4,
                'usage_bw_a4' => $bwA4,

                'current_color_a3' => $currentColorA3,
                'previous_color_a3' => $previousColorA3,
                'usage_color_a3' => $colorA3,

                'current_color_a4' => $currentColorA4,
                'previous_color_a4' => $previousColorA4,
                'usage_color_a4' => $colorA4,

                'current_bw_long_sheet' => $currentBwLongSheet,
                'previous_bw_long_sheet' => $previousBwLongSheet,
                'usage_bw_long_sheet' => $bwLongSheet,

                'current_color_long_sheet' => $currentColorLongSheet,
                'previous_color_long_sheet' => $previousColorLongSheet,
                'usage_color_long_sheet' => $colorLongSheet,
            ];

            $item->billing_detail = [
                'billing_rule' => $billingRule,

                'total_a3_click' => $totalA3Click,
                'total_a4_click' => $totalA4Click,
                'total_pemakaian_click' => $totalPemakaianClick,

                'minimum_charge_size' => $minimumSize,
                'minimum_charge_click' => $minimumClick,
                'minimum_charge_nominal' => $minimumNominal,
                'minimum_basis_click' => $minimumBasisClick,
                'has_minimum_rule' => $hasMinimumRule,

                'harga_color_a3' => $hargaColorA3,
                'harga_color_a4' => $hargaColorA4,
                'harga_bw_a3' => $hargaBwA3,
                'harga_bw_a4' => $hargaBwA4,

                'over_click_color_a3' => $overColorA3,
                'over_click_color_a4' => $overColorA4,
                'over_click_bw_a3' => $overBwA3,
                'over_click_bw_a4' => $overBwA4,

                'rate_color_a3' => $rateColorA3,
                'rate_color_a4' => $rateColorA4,
                'rate_bw_a3' => $rateBwA3,
                'rate_bw_a4' => $rateBwA4,

                'biaya_bw_a3' => $biayaBwA3,
                'biaya_bw_a4' => $biayaBwA4,
                'biaya_color_a3' => $biayaColorA3,
                'biaya_color_a4' => $biayaColorA4,
                'biaya_bw_long_sheet' => $biayaBwLongSheet,
                'biaya_color_long_sheet' => $biayaColorLongSheet,

                'subtotal_sebelum_free' => $subtotalSebelumFree,
                'subtotal_setelah_free' => $subtotalSetelahFree,

                'free_klik_percent' => $freePercent,
                'free_klik' => $freeKlik,
                'nilai_free_klik' => $nilaiFreeKlik,

                'total_tagihan' => $totalTagihan,
            ];

            $item->usage_bw_a3 = $bwA3;
            $item->usage_bw_a4 = $bwA4;
            $item->usage_color_a3 = $colorA3;
            $item->usage_color_a4 = $colorA4;
            $item->usage_bw_long_sheet = $bwLongSheet;
            $item->usage_color_long_sheet = $colorLongSheet;

            $item->total_a3_click = $totalA3Click;
            $item->total_a4_click = $totalA4Click;
            $item->total_pemakaian_click = $totalPemakaianClick;
            $item->minimum_basis_click = $minimumBasisClick;
            $item->billing_rule = $billingRule;
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