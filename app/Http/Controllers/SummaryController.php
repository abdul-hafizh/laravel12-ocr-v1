<?php

namespace App\Http\Controllers;

use App\Exports\ElectricitySummaryExport;
use App\Exports\PrinterBillingExport;
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

        foreach ($summary as $index => $item) {
            $summary[$index]['foto_awal'] = DB::table('v_image_scan_electricity')
                ->where('cabang_id', $item['cabang_id'])
                ->where('nomor_meter', $item['nomor_meter'])
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at', 'asc')
                ->value('image_path');

            $summary[$index]['foto_akhir'] = DB::table('v_image_scan_electricity')
                ->where('cabang_id', $item['cabang_id'])
                ->where('nomor_meter', $item['nomor_meter'])
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at', 'desc')
                ->value('image_path');
        }

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

        $billings->getCollection()->transform(function ($item) use ($periodeStart, $periodeEnd) {
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

            $currentScan = DB::table('dbo.v_image_scan_printers')
                ->where('serial_number', $item->serial_number)
                ->whereBetween('created_at', [$periodeStart, $periodeEnd])
                ->orderByDesc('created_at')
                ->first();

            $firstScan = DB::table('dbo.v_image_scan_printers')
                ->where('serial_number', $item->serial_number)
                ->whereBetween('created_at', [$periodeStart, $periodeEnd])
                ->orderBy('created_at', 'asc')
                ->first();

            $item->foto_awal = $firstScan?->image_path;
            $item->foto_akhir = $currentScan?->image_path;

            $item->notes = DB::table('scan_notes')
                ->leftJoin('users', 'users.id', '=', 'scan_notes.user_id')
                ->where('scan_notes.image_scan_id', $item->id)
                ->select(
                    'scan_notes.id',
                    'scan_notes.note',
                    'scan_notes.created_at',
                    'users.name as user_name'
                )
                ->orderBy('scan_notes.created_at')
                ->get();

            $item->new_note = '';

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
            return back()->with('message', ['text' => 'Tidak ada user finance yang memiliki nomor WhatsApp.', 'type' => 'error']);
        }

        foreach ($financeUsers as $user) {
            SendSms::sendDocumentWA(
                $user->phone,
                $fileUrl,
                "Summary Token Listrik periode {$periodeLabel}"
            );
        }

        return back()->with('message', ['text' => 'File Excel summary berhasil dikirim ke semua finance.', 'type' => 'success']);
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
            $ppnPersen = $this->toFloat($awal->ppn_persen ?? 0);

            $estimasiBiayaStroom = $pemakaianKwh * $hargaPerKwh;
            $estimasiPpn = $estimasiBiayaStroom * ($ppnPersen / 100);
            $estimasiTotalDenganPpn = $estimasiBiayaStroom + $estimasiPpn;

            $estimasiSisaStroom = $kwhAkhir * $hargaPerKwh;
            $estimasiSisaPpn = $estimasiSisaStroom * ($ppnPersen / 100);
            $estimasiSisaRupiah = $estimasiSisaStroom + $estimasiSisaPpn;

            $rekomendasiTopupBulanDepan = max(
                $estimasiTotalDenganPpn - $estimasiSisaRupiah,
                0
            );

            $summary[] = [
                'cabang_id' => $awal->cabang_id,
                'nama_cabang' => $awal->nama_cabang,
                'kode_cabang' => $awal->kode_cabang,

                'master_token_listrik_id' => $awal->master_token_listrik_id,
                'master_daya_listrik_id' => $awal->master_daya_listrik_id ?? null,

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

                'ppn_persen' => round($ppnPersen, 2),

                'estimasi_biaya_stroom' => round($estimasiBiayaStroom, 2),
                'estimasi_ppn' => round($estimasiPpn, 2),
                'estimasi_total_dengan_ppn' => round($estimasiTotalDenganPpn, 2),

                'estimasi_sisa_stroom' => round($estimasiSisaStroom, 2),
                'estimasi_sisa_ppn' => round($estimasiSisaPpn, 2),
                'estimasi_sisa_rupiah' => round($estimasiSisaRupiah, 2),

                'estimasi_pemakaian_rupiah' => round($estimasiTotalDenganPpn, 2),

                'rekomendasi_topup_bulan_depan' => round($rekomendasiTopupBulanDepan, 2),

                'jumlah_foto' => $items->count(),

                'image_scan_id_awal' => $awal->id,
                'image_scan_id_akhir' => $akhir->id,

                'notes' => DB::table('scan_notes')
                    ->leftJoin('users', 'users.id', '=', 'scan_notes.user_id')
                    ->where('scan_notes.image_scan_id', $akhir->id)
                    ->select(
                        'scan_notes.id',
                        'scan_notes.note',
                        'scan_notes.created_at',
                        'users.name as user_name'
                    )
                    ->orderBy('scan_notes.created_at')
                    ->get(),

                'notes_text' => DB::table('scan_notes')
                    ->leftJoin('users', 'users.id', '=', 'scan_notes.user_id')
                    ->where('scan_notes.image_scan_id', $akhir->id)
                    ->select(
                        'scan_notes.note',
                        'users.name as user_name'
                    )
                    ->orderBy('scan_notes.created_at')
                    ->get()
                    ->map(function ($note) {
                        return ($note->user_name ?? '-') . ': ' . ($note->note ?? '-');
                    })
                    ->implode("\n") ?: '-',

                'new_note' => '',

                'status_summary' => $this->getStatusSummary(
                    $items,
                    $kwhAwal,
                    $kwhAkhir,
                    $hargaPerKwh,
                    $ppnPersen,
                    $awal->master_token_listrik_id,
                    $awal->token_is_active,
                    $awal->master_daya_listrik_id ?? null,
                    $awal->daya_is_active ?? null
                ),
            ];
        }

        return $summary;
    }

    public function storeScanNote(Request $request)
    {
        $validated = $request->validate([
            'image_scan_id' => ['required', 'exists:image_scans,id'],
            'cabang_id' => ['nullable', 'exists:master_cabangs,id'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::table('scan_notes')->insert([
            'image_scan_id' => $validated['image_scan_id'],
            'cabang_id' => $validated['cabang_id'] ?? null,
            'user_id' => auth()->id(),
            'note' => $validated['note'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('message', [
            'type' => 'success',
            'text' => 'Catatan berhasil disimpan.',
        ]);
    }

    public function sendPrinterBillingWa(Request $request)
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
            ->whereBetween('p.created_at', [$periodeStart, $periodeEnd])
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
            ->get();

        if ($billings->isEmpty()) {
            return back()->with('message', [
                'text' => 'Tidak ada data billing printer untuk dikirim.',
                'type' => 'error',
            ]);
        }

        $billings = $billings->map(function ($item) {
            $notes = DB::table('scan_notes')
                ->leftJoin('users', 'users.id', '=', 'scan_notes.user_id')
                ->where('scan_notes.image_scan_id', $item->id)
                ->select('scan_notes.note', 'users.name as user_name')
                ->orderBy('scan_notes.created_at')
                ->get()
                ->map(function ($note) {
                    return ($note->user_name ?? '-') . ': ' . ($note->note ?? '-');
                })
                ->implode("\n");

            $item->notes_text = $notes ?: '-';

            return $item;
        });

        $periodeLabel = Carbon::parse($startDate)->format('d-m-Y') . '_sd_' . Carbon::parse($endDate)->format('d-m-Y');

        $fileName = 'billing-printer-' . $periodeLabel . '-' . now()->format('His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        $excelFile = Excel::raw(
            new PrinterBillingExport($billings),
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
            return back()->with('message', [
                'text' => 'Tidak ada user finance yang memiliki nomor WhatsApp.',
                'type' => 'error',
            ]);
        }

        foreach ($financeUsers as $user) {
            SendSms::sendDocumentWA(
                $user->phone,
                $fileUrl,
                "Billing Meter Printer periode {$periodeLabel}"
            );
        }

        return back()->with('message', [
            'text' => 'File Excel billing printer berhasil dikirim ke semua finance.',
            'type' => 'success',
        ]);
    }

    public function asabaBilling(Request $request)
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

        $query = DB::table('dbo.v_image_scan_asaba as p')
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
                    FROM dbo.v_image_scan_asaba p2
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

        $billings->getCollection()->transform(function ($item) use ($periodeStart, $periodeEnd) {
            $previousScan = DB::table('dbo.v_image_scan_asaba')
                ->where('serial_number', $item->serial_number)
                ->where('created_at', '<=', $periodeStart)
                ->orderByDesc('created_at')
                ->first();

            $getCounter = function ($value) {
                return (int) preg_replace('/[^0-9]/', '', (string) ($value ?? 0));
            };

            $currentTotal = $getCounter($item->total_counter ?? 0);
            $currentPrinter = $getCounter($item->printer_counter ?? 0);
            $currentCopy = $getCounter($item->copy_counter ?? 0);
            $currentScan = $getCounter($item->scan_counter ?? 0);
            $currentFeedPaper = $getCounter($item->feed_paper_counter ?? 0);
            $currentOutputPaper = $getCounter($item->output_paper_counter ?? 0);
            $currentFullColor = $getCounter($item->full_color_counter ?? 0);
            $currentSingleColor = $getCounter($item->single_color_counter ?? 0);
            $currentBlack = $getCounter($item->black_counter ?? 0);

            $previousTotal = $getCounter($previousScan->total_counter ?? 0);
            $previousPrinter = $getCounter($previousScan->printer_counter ?? 0);
            $previousCopy = $getCounter($previousScan->copy_counter ?? 0);
            $previousScanCounter = $getCounter($previousScan->scan_counter ?? 0);
            $previousFeedPaper = $getCounter($previousScan->feed_paper_counter ?? 0);
            $previousOutputPaper = $getCounter($previousScan->output_paper_counter ?? 0);
            $previousFullColor = $getCounter($previousScan->full_color_counter ?? 0);
            $previousSingleColor = $getCounter($previousScan->single_color_counter ?? 0);
            $previousBlack = $getCounter($previousScan->black_counter ?? 0);

            if (!$previousScan) {
                $usageTotal = 0;
                $usagePrinter = 0;
                $usageCopy = 0;
                $usageScan = 0;
                $usageFeedPaper = 0;
                $usageOutputPaper = 0;
                $usageFullColor = 0;
                $usageSingleColor = 0;
                $usageBlack = 0;
            } else {
                $usageTotal = max(0, $currentTotal - $previousTotal);
                $usagePrinter = max(0, $currentPrinter - $previousPrinter);
                $usageCopy = max(0, $currentCopy - $previousCopy);
                $usageScan = max(0, $currentScan - $previousScanCounter);
                $usageFeedPaper = max(0, $currentFeedPaper - $previousFeedPaper);
                $usageOutputPaper = max(0, $currentOutputPaper - $previousOutputPaper);
                $usageFullColor = max(0, $currentFullColor - $previousFullColor);
                $usageSingleColor = max(0, $currentSingleColor - $previousSingleColor);
                $usageBlack = max(0, $currentBlack - $previousBlack);
            }

            $hargaColorA4 = (float) ($item->harga_color_a4 ?? 0);
            $hargaBwA4 = (float) ($item->harga_bw_a4 ?? 0);

            $overColorA4 = (float) ($item->over_click_color_a4 ?? 0);
            $overBwA4 = (float) ($item->over_click_bw_a4 ?? 0);

            $rateColorA4 = $overColorA4 > 0 ? $overColorA4 : $hargaColorA4;
            $rateBwA4 = $overBwA4 > 0 ? $overBwA4 : $hargaBwA4;

            /*
            * Aturan Asaba:
            * - Jika counter Full Color / Black tersedia, pakai itu.
            * - Single Color digabung ke color.
            * - Jika mesin BW dan tidak ada Black Counter, fallback ke Printer Counter.
            * - Jika Printer Counter kosong, fallback ke Total Counter.
            */
            $usageColorBilling = $usageFullColor + $usageSingleColor;

            if ($usageBlack > 0) {
                $usageBwBilling = $usageBlack;
                $counterSource = 'black_color_counter';
            } elseif ($usagePrinter > 0) {
                $usageBwBilling = $usagePrinter;
                $counterSource = 'printer_counter';
            } else {
                $usageBwBilling = $usageTotal;
                $counterSource = 'total_counter';
            }

            if ($hargaColorA4 <= 0 && $usageColorBilling <= 0) {
                $usageBwBilling = $usagePrinter > 0 ? $usagePrinter : $usageTotal;
                $counterSource = 'bw_machine';
            }

            $totalPemakaianClick = $usageBwBilling + $usageColorBilling;

            $biayaBw = $usageBwBilling * $rateBwA4;
            $biayaColor = $usageColorBilling * $rateColorA4;

            $subtotalBilling = $biayaBw + $biayaColor;
            $subtotalSebelumFree = $subtotalBilling;

            $minimumClick = (int) ($item->minimum_charge_click ?? 0);
            $minimumNominal = (float) ($item->minimum_charge_nominal ?? 0);
            $minimumSize = strtoupper((string) ($item->minimum_charge_size ?? 'A4'));
            $freePercent = (float) ($item->free_klik_percent ?? 0);

            $hasMinimumRule =
                $minimumClick > 0
                && $minimumNominal > 0;

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
            } elseif ($hasMinimumRule && $totalPemakaianClick < $minimumClick) {
                $totalTagihan = $minimumNominal;
                $billingRule = 'minimum_charge_' . strtolower($minimumSize ?: 'a4');
            } else {
                $totalTagihan = $subtotalSetelahFree;

                if ($hasMinimumRule && $totalPemakaianClick >= $minimumClick) {
                    $billingRule = 'over_minimum_' . strtolower($minimumSize ?: 'a4');
                } else {
                    $billingRule = 'standard';
                }
            }

            $item->counter_detail = [
                'has_previous_scan' => $previousScan ? true : false,
                'previous_created_at' => $previousScan->created_at ?? null,
                'current_created_at' => $item->created_at,

                'current_total_counter' => $currentTotal,
                'previous_total_counter' => $previousTotal,
                'usage_total_counter' => $usageTotal,

                'current_printer_counter' => $currentPrinter,
                'previous_printer_counter' => $previousPrinter,
                'usage_printer_counter' => $usagePrinter,

                'current_copy_counter' => $currentCopy,
                'previous_copy_counter' => $previousCopy,
                'usage_copy_counter' => $usageCopy,

                'current_scan_counter' => $currentScan,
                'previous_scan_counter' => $previousScanCounter,
                'usage_scan_counter' => $usageScan,

                'current_feed_paper_counter' => $currentFeedPaper,
                'previous_feed_paper_counter' => $previousFeedPaper,
                'usage_feed_paper_counter' => $usageFeedPaper,

                'current_output_paper_counter' => $currentOutputPaper,
                'previous_output_paper_counter' => $previousOutputPaper,
                'usage_output_paper_counter' => $usageOutputPaper,

                'current_full_color_counter' => $currentFullColor,
                'previous_full_color_counter' => $previousFullColor,
                'usage_full_color_counter' => $usageFullColor,

                'current_single_color_counter' => $currentSingleColor,
                'previous_single_color_counter' => $previousSingleColor,
                'usage_single_color_counter' => $usageSingleColor,

                'current_black_counter' => $currentBlack,
                'previous_black_counter' => $previousBlack,
                'usage_black_counter' => $usageBlack,
            ];

            $item->billing_detail = [
                'billing_rule' => $billingRule,
                'counter_source' => $counterSource,

                'usage_bw_billing' => $usageBwBilling,
                'usage_color_billing' => $usageColorBilling,
                'total_pemakaian_click' => $totalPemakaianClick,

                'harga_bw_a4' => $hargaBwA4,
                'harga_color_a4' => $hargaColorA4,

                'over_click_bw_a4' => $overBwA4,
                'over_click_color_a4' => $overColorA4,

                'rate_bw_a4' => $rateBwA4,
                'rate_color_a4' => $rateColorA4,

                'biaya_bw' => $biayaBw,
                'biaya_color' => $biayaColor,

                'subtotal_sebelum_free' => $subtotalSebelumFree,
                'subtotal_setelah_free' => $subtotalSetelahFree,

                'free_klik_percent' => $freePercent,
                'free_klik' => $freeKlik,
                'nilai_free_klik' => $nilaiFreeKlik,

                'minimum_charge_size' => $minimumSize,
                'minimum_charge_click' => $minimumClick,
                'minimum_charge_nominal' => $minimumNominal,
                'has_minimum_rule' => $hasMinimumRule,

                'total_tagihan' => $totalTagihan,
            ];

            $item->usage_total_counter = $usageTotal;
            $item->usage_printer_counter = $usagePrinter;
            $item->usage_copy_counter = $usageCopy;
            $item->usage_scan_counter = $usageScan;
            $item->usage_feed_paper_counter = $usageFeedPaper;
            $item->usage_output_paper_counter = $usageOutputPaper;
            $item->usage_full_color_counter = $usageFullColor;
            $item->usage_single_color_counter = $usageSingleColor;
            $item->usage_black_counter = $usageBlack;

            $item->usage_bw_billing = $usageBwBilling;
            $item->usage_color_billing = $usageColorBilling;
            $item->total_pemakaian_click = $totalPemakaianClick;
            $item->billing_rule = $billingRule;
            $item->counter_source = $counterSource;
            $item->total_tagihan = $totalTagihan;

            $currentScan = DB::table('dbo.v_image_scan_asaba')
                ->where('serial_number', $item->serial_number)
                ->whereBetween('created_at', [$periodeStart, $periodeEnd])
                ->orderByDesc('created_at')
                ->first();

            $firstScan = DB::table('dbo.v_image_scan_asaba')
                ->where('serial_number', $item->serial_number)
                ->whereBetween('created_at', [$periodeStart, $periodeEnd])
                ->orderBy('created_at', 'asc')
                ->first();

            $item->foto_awal = $firstScan?->image_path;
            $item->foto_akhir = $currentScan?->image_path;

            $item->notes = DB::table('scan_notes')
                ->leftJoin('users', 'users.id', '=', 'scan_notes.user_id')
                ->where('scan_notes.image_scan_id', $item->id)
                ->select(
                    'scan_notes.id',
                    'scan_notes.note',
                    'scan_notes.created_at',
                    'users.name as user_name'
                )
                ->orderBy('scan_notes.created_at')
                ->get();

            $item->new_note = '';

            return $item;
        });

        if ($request->boolean('debug')) {
            dd($billings->items());
        }

        return Inertia::render('Summary/Asaba', [
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

    public function ceaBilling(Request $request)
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

        $query = DB::table('dbo.v_image_scan_cea as p')
            ->leftJoin('dbo.master_mesins as mm', 'mm.id', '=', 'p.master_mesin_id')
            ->select([
                'p.*',
                'mm.keterangan as master_keterangan',
            ])
            ->whereBetween('p.created_at', [$periodeStart, $periodeEnd])
            ->whereRaw("
                p.created_at = (
                    SELECT MAX(p2.created_at)
                    FROM dbo.v_image_scan_cea p2
                    WHERE p2.serial_number = p.serial_number
                    AND p2.created_at BETWEEN ? AND ?
                )
            ", [$periodeStart, $periodeEnd]);

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

        $billings->getCollection()->transform(function ($item) use ($periodeStart, $periodeEnd) {
            $previousScan = DB::table('dbo.v_image_scan_cea')
                ->where('serial_number', $item->serial_number)
                ->where('created_at', '<=', $periodeStart)
                ->orderByDesc('created_at')
                ->first();

            $getCounter = function ($value) {
                return (int) preg_replace('/[^0-9]/', '', (string) ($value ?? 0));
            };

            $currentTotal = $getCounter($item->total_counter_mesin ?? 0);
            $currentPrint = $getCounter($item->print_counter ?? 0);
            $currentCopy = $getCounter($item->copy_counter ?? 0);

            $previousTotal = $getCounter($previousScan->total_counter_mesin ?? 0);
            $previousPrint = $getCounter($previousScan->print_counter ?? 0);
            $previousCopy = $getCounter($previousScan->copy_counter ?? 0);

            if (!$previousScan) {
                $usageTotal = 0;
                $usagePrint = 0;
                $usageCopy = 0;
            } else {
                $usageTotal = max(0, $currentTotal - $previousTotal);
                $usagePrint = max(0, $currentPrint - $previousPrint);
                $usageCopy = max(0, $currentCopy - $previousCopy);
            }

            $totalMeter = $usagePrint + $usageCopy;

            $printPercent = $totalMeter > 0
                ? round(($usagePrint / $totalMeter) * 100, 2)
                : 0;

            $copyPercent = $totalMeter > 0
                ? round(($usageCopy / $totalMeter) * 100, 2)
                : 0;

            $contractService = 300000;

            $printBilling = $totalMeter > 0
                ? round($contractService * ($printPercent / 100))
                : 0;

            $copyBilling = $totalMeter > 0
                ? round($contractService * ($copyPercent / 100))
                : 0;

            $totalTagihan = $previousScan && $totalMeter > 0
                ? $contractService
                : 0;

            if (!$previousScan) {
                $billingRule = 'no_previous_scan';
            } elseif ($totalMeter <= 0) {
                $billingRule = 'no_usage';
            } else {
                $billingRule = 'contract_service';
            }

            $item->counter_detail = [
                'has_previous_scan' => $previousScan ? true : false,
                'previous_created_at' => $previousScan->created_at ?? null,
                'current_created_at' => $item->created_at,

                'current_total_counter_mesin' => $currentTotal,
                'previous_total_counter_mesin' => $previousTotal,
                'usage_total_counter_mesin' => $usageTotal,

                'current_print_counter' => $currentPrint,
                'previous_print_counter' => $previousPrint,
                'usage_print_counter' => $usagePrint,

                'current_copy_counter' => $currentCopy,
                'previous_copy_counter' => $previousCopy,
                'usage_copy_counter' => $usageCopy,
            ];

            $item->billing_detail = [
                'billing_rule' => $billingRule,

                'contract_service' => $contractService,

                'usage_print' => $usagePrint,
                'usage_copy' => $usageCopy,
                'usage_total_counter_mesin' => $usageTotal,

                'total_meter' => $totalMeter,

                'print_percent' => $printPercent,
                'copy_percent' => $copyPercent,

                'print_billing' => $printBilling,
                'copy_billing' => $copyBilling,

                'total_tagihan' => $totalTagihan,
            ];

            $item->usage_total_counter_mesin = $usageTotal;
            $item->usage_print_counter = $usagePrint;
            $item->usage_copy_counter = $usageCopy;

            $item->total_meter = $totalMeter;
            $item->print_percent = $printPercent;
            $item->copy_percent = $copyPercent;

            $item->contract_service = $contractService;
            $item->print_billing = $printBilling;
            $item->copy_billing = $copyBilling;

            $item->billing_rule = $billingRule;
            $item->total_tagihan = $totalTagihan;

            $currentScan = DB::table('dbo.v_image_scan_cea')
                ->where('serial_number', $item->serial_number)
                ->whereBetween('created_at', [$periodeStart, $periodeEnd])
                ->orderByDesc('created_at')
                ->first();

            $firstScan = DB::table('dbo.v_image_scan_cea')
                ->where('serial_number', $item->serial_number)
                ->whereBetween('created_at', [$periodeStart, $periodeEnd])
                ->orderBy('created_at', 'asc')
                ->first();

            $item->foto_awal = $firstScan?->image_path;
            $item->foto_akhir = $currentScan?->image_path;

            $item->notes = DB::table('scan_notes')
                ->leftJoin('users', 'users.id', '=', 'scan_notes.user_id')
                ->where('scan_notes.image_scan_id', $item->id)
                ->select(
                    'scan_notes.id',
                    'scan_notes.note',
                    'scan_notes.created_at',
                    'users.name as user_name'
                )
                ->orderBy('scan_notes.created_at')
                ->get();

            $cadanganPrintCea = 750000;

            $totalLaporan = $copyBilling + $printBilling + $cadanganPrintCea;

            $item->laporan_detail = [
                'biaya_fotocopy' => $copyBilling,
                'biaya_print_bw' => $printBilling,
                'cadangan_print_cea' => $cadanganPrintCea,
                'total_laporan' => $totalLaporan,
            ];

            $item->new_note = '';

            return $item;
        });

        if ($request->boolean('debug')) {
            dd($billings->items());
        }        

        return Inertia::render('Summary/Cea', [
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

    private function getStatusSummary(
        $items,
        float $kwhAwal,
        float $kwhAkhir,
        float $hargaPerKwh,
        float $ppnPersen,
        $masterTokenId = null,
        $tokenIsActive = null,
        $masterDayaListrikId = null,
        $dayaIsActive = null
    ): string {
        if (empty($masterTokenId)) {
            return 'Master tidak ditemukan';
        }

        if ((int) $tokenIsActive === 0) {
            return 'Master token tidak aktif';
        }

        if (empty($masterDayaListrikId)) {
            return 'Master daya belum diisi';
        }

        if ((int) $dayaIsActive === 0) {
            return 'Master daya tidak aktif';
        }

        if ($items->count() < 2) {
            return 'Belum lengkap';
        }

        if ($hargaPerKwh <= 0) {
            return 'Harga/kWh belum diisi';
        }

        if ($ppnPersen < 0) {
            return 'PPN tidak valid';
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

    public function deleteScanNote($id)
    {
        $note = DB::table('scan_notes')
            ->where('id', $id)
            ->first();

        if (!$note) {
            return back()->with('message', [
                'type' => 'error',
                'text' => 'Catatan tidak ditemukan.',
            ]);
        }

        DB::table('scan_notes')
            ->where('id', $id)
            ->delete();

        return back()->with('message', [
            'type' => 'success',
            'text' => 'Catatan berhasil dihapus.',
        ]);
    }
}