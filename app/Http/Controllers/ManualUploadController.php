<?php

namespace App\Http\Controllers;

use App\Helpers\UserAccessHelper;
use App\Jobs\AnalyzeImageJob;
use App\Models\ImageScan;
use App\Models\MasterCabang;
use App\Models\MasterMesin;
use App\Models\MasterTokenListrik;
use App\Services\ManualUpload\ManualUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ManualUploadController extends Controller
{
    private const SCAN_TYPES = [
        'online_receipt' => 'Bukti Bayar',
        'electricity' => 'Token Listrik',
        'printer' => 'Mesin Samafitro',
        'cea' => 'Mesin CEA',
        'asaba' => 'Mesin Asaba',
        'part_maintenance' => 'Part / Maintenance',
    ];

    private const MACHINE_SCAN_TYPES = ['printer', 'cea', 'asaba', 'part_maintenance'];

    private const EDITABLE_FIELDS = [
        'electricity' => ['tanggal', 'kwh', 'barcode', 'nomor_meter', 'nomor_token', 'lokasi', 'alamat_lengkap', 'kecamatan', 'kota', 'provinsi'],
        'printer' => ['tanggal', 'lokasi', 'bw_a3', 'bw_a4', 'color_a3', 'color_a4', 'total_long_sheet', 'bw_long_sheet', 'color_long_sheet'],
        'cea' => ['tanggal', 'lokasi', 'total_counter_mesin', 'print_counter', 'copy_counter'],
        'asaba' => ['tanggal', 'lokasi', 'total_counter', 'printer_counter', 'copy_counter', 'scan_counter', 'feed_paper_counter', 'output_paper_counter', 'full_color_counter', 'single_color_counter', 'black_counter'],
        'part_maintenance' => ['jenis_gambar', 'deskripsi_gambar', 'catatan', 'nominal_chat'],
        'online_receipt' => [
            'jenis_struk', 'bank_atau_aplikasi', 'status_transaksi', 'tanggal_transaksi', 'waktu_transaksi',
            'total_pembayaran', 'rekening_sumber', 'nama_rekening_sumber', 'terminal', 'jenis_pembelian',
            'nomor_transaksi', 'nomor_struk', 'nomor_referensi', 'nama_toko', 'nama_pembeli', 'nomor_pesanan',
            'metode_pembayaran', 'produk', 'provider', 'nomor_meter', 'id_pelanggan', 'nama_pelanggan',
            'tarif_daya', 'no_ref', 'rp_bayar', 'materai', 'ppn', 'ppj_tl', 'angsuran', 'rp_stroom_token',
            'jumlah_kwh', 'stroom_token', 'admin_bank', 'catatan',
        ],
    ];

    public function __construct(private ManualUploadService $manualUploadService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $cabangIds = UserAccessHelper::cabangIds($user);

        $query = ImageScan::query()
            ->with([
                'user:id,name,phone,email',
                'cabang:id,kode_cabang,nama_cabang',
                'mesin:id,nama_mesin,serial_number',
            ])
            ->whereIn('cabang_id', $cabangIds);

        if ($request->filled('scan_type')) {
            $query->where('scan_type', $request->scan_type);
        }

        if ($request->filled('cabang_id') && $cabangIds->contains((int) $request->cabang_id)) {
            $query->where('cabang_id', $request->cabang_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('cabang', function ($c) use ($search) {
                    $c->where('nama_cabang', 'like', "%{$search}%")
                        ->orWhere('kode_cabang', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $scans = $query
            ->orderByDesc(function ($sub) use ($request) {
                $sub->selectRaw('MAX(created_at)')
                    ->from('image_scans as latest_scan')
                    ->whereColumn('latest_scan.cabang_id', 'image_scans.cabang_id');

                if ($request->filled('scan_type')) {
                    $sub->where('latest_scan.scan_type', $request->scan_type);
                }

                if ($request->filled('search')) {
                    $search = trim($request->search);

                    $sub->where(function ($q) use ($search) {
                        $q->whereIn('latest_scan.user_id', function ($uq) use ($search) {
                            $uq->select('id')->from('users')
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })->orWhereIn('latest_scan.cabang_id', function ($cq) use ($search) {
                            $cq->select('id')->from('master_cabangs')
                                ->where('nama_cabang', 'like', "%{$search}%")
                                ->orWhere('kode_cabang', 'like', "%{$search}%");
                        });
                    });
                }

                if ($request->filled('date_from')) {
                    $sub->whereDate('latest_scan.created_at', '>=', $request->date_from);
                }

                if ($request->filled('date_to')) {
                    $sub->whereDate('latest_scan.created_at', '<=', $request->date_to);
                }
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $cabangs = MasterCabang::with(['users:id,name,phone,email'])
            ->whereIn('id', $cabangIds)
            ->where('is_active', true)
            ->orderBy('nama_cabang')
            ->get(['id', 'kode_cabang', 'nama_cabang']);

        $mesins = MasterMesin::whereIn('master_cabang_id', $cabangIds)
            ->where('is_active', true)
            ->with('maintenanceParts:id,master_mesin_id,nama_part')
            ->orderBy('nama_mesin')
            ->get(['id', 'master_cabang_id', 'nama_mesin', 'serial_number']);

        $tokens = MasterTokenListrik::whereIn('master_cabang_id', $cabangIds)
            ->where('is_active', true)
            ->orderBy('nomor_meter')
            ->get(['id', 'master_cabang_id', 'nomor_meter', 'nama_pelanggan']);

        return Inertia::render('Ocr/ManualUpload/Index', [
            'scans' => $scans,
            'cabangs' => $cabangs,
            'mesins' => $mesins,
            'tokens' => $tokens,
            'scanTypes' => collect(self::SCAN_TYPES)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
            ])->values(),
            'filters' => $request->only(['scan_type', 'cabang_id', 'search', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $allowedCabangIds = UserAccessHelper::cabangIds($user);

        $validated = $request->validate([
            'scan_type' => ['required', Rule::in(array_keys(self::SCAN_TYPES))],
            'cabang_id' => ['required', Rule::in($allowedCabangIds->all())],
            'user_id' => ['required', 'exists:users,id'],
            'created_at' => ['required', 'date'],
            'image' => ['required', 'image', 'max:10240'],
            'master_mesin_id' => [
                Rule::requiredIf(in_array($request->scan_type, self::MACHINE_SCAN_TYPES, true)),
                'nullable',
                'exists:master_mesins,id',
            ],
            'master_mesin_part_id' => ['nullable', 'exists:master_mesin_parts,id'],
            'nominal' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cabang = MasterCabang::findOrFail($validated['cabang_id']);

        if (!$cabang->users()->where('users.id', $validated['user_id'])->exists()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'User yang dipilih bukan bagian dari cabang tersebut.',
            ], 422);
        }

        $mesin = null;
        if (!empty($validated['master_mesin_id'])) {
            $mesin = MasterMesin::where('id', $validated['master_mesin_id'])
                ->where('master_cabang_id', $validated['cabang_id'])
                ->where('is_active', true)
                ->first();

            if (!$mesin) {
                return response()->json([
                    'status' => 'invalid',
                    'message' => 'Mesin yang dipilih tidak valid untuk cabang tersebut.',
                ], 422);
            }
        }

        $file = $request->file('image');
        $imageBody = file_get_contents($file->getRealPath());
        $mimeType = $file->getMimeType();

        $validation = $this->manualUploadService->validateImage($validated['scan_type'], $imageBody, $mimeType);

        if (!($validation['valid'] ?? false)) {
            return response()->json([
                'status' => 'invalid',
                'message' => $validation['message'] ?? 'Data wajib tidak ditemukan pada gambar.',
            ], 422);
        }

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'manual_' . $validated['cabang_id'] . '_' . time() . '.' . $extension;
        $path = 'image-scans/' . $filename;

        Storage::disk('public')->put($path, $imageBody);

        $dataPenting = $validation['data'] ?? [];

        if ($validated['scan_type'] === 'part_maintenance') {
            $dataPenting['nominal_chat'] = isset($validated['nominal']) ? (int) $validated['nominal'] : null;
        }

        $scan = ImageScan::create([
            'user_id' => $validated['user_id'],
            'cabang_id' => $validated['cabang_id'],
            'master_mesin_id' => $mesin?->id,
            'master_mesin_part_id' => $validated['master_mesin_part_id'] ?? null,
            'scan_type' => $validated['scan_type'],
            'image_path' => $path,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'status' => 'pending',
            'analysis_result' => [
                'valid' => true,
                'message' => $validation['message'] ?? null,
                'data_penting' => array_merge($dataPenting, [
                    'manual_upload_by' => $user->id,
                ]),
            ],
        ]);

        $scan->forceFill(['created_at' => Carbon::parse($validated['created_at'])])->save();

        if ($validated['scan_type'] === 'electricity') {
            $match = $this->manualUploadService->resolveElectricityMatch($dataPenting);

            if (!$match['nomor_meter'] || !$match['master_token']) {
                $scan->update([
                    'status' => 'failed',
                    'error_message' => 'Nomor meter tidak ditemukan di database.',
                ]);

                return response()->json([
                    'status' => 'needs_correction',
                    'correction' => 'electricity_meter',
                    'scan_id' => $scan->id,
                    'candidates' => $this->manualUploadService->tokenCandidates((int) $validated['cabang_id'])
                        ->map(fn ($t) => ['id' => $t->id, 'label' => "{$t->nomor_meter} ({$t->nama_pelanggan})"]),
                ]);
            }

            if (!$match['kwh_valid']) {
                $analysis = $scan->analysis_result;
                $analysis['data_penting']['nomor_meter'] = $match['nomor_meter'];
                $analysis['data_penting']['master_token_listrik'] = [
                    'id' => $match['master_token']->id,
                    'nomor_meter' => $match['master_token']->nomor_meter,
                ];

                $scan->update([
                    'status' => 'failed',
                    'analysis_result' => $analysis,
                    'error_message' => 'kWh tidak terbaca jelas.',
                ]);

                return response()->json([
                    'status' => 'needs_correction',
                    'correction' => 'electricity_kwh',
                    'scan_id' => $scan->id,
                    'nomor_meter' => $match['nomor_meter'],
                ]);
            }

            $analysis = $scan->analysis_result;
            $analysis['data_penting']['nomor_meter'] = $match['nomor_meter'];
            $analysis['data_penting']['kwh'] = $this->manualUploadService->normalizeKwh($match['kwh']);
            $analysis['data_penting']['master_token_listrik'] = [
                'id' => $match['master_token']->id,
                'nomor_meter' => $match['master_token']->nomor_meter,
            ];

            $scan->update(['analysis_result' => $analysis]);
        }

        if (in_array($validated['scan_type'], ['printer', 'cea', 'asaba'], true) && $mesin) {
            $analysis = $scan->analysis_result;
            $analysis['data_penting']['serial_number'] = $mesin->serial_number;
            $analysis['data_penting']['master_mesin'] = [
                'id' => $mesin->id,
                'nama_mesin' => $mesin->nama_mesin,
                'serial_number' => $mesin->serial_number,
            ];

            if ($validated['scan_type'] === 'printer') {
                $analysis['data_penting']['perhitungan'] = $this->manualUploadService->printerPerhitungan($dataPenting, $mesin);
            }

            $scan->update(['analysis_result' => $analysis]);
        }

        AnalyzeImageJob::dispatch($scan->id);

        return response()->json([
            'status' => 'success',
            'scan' => $scan->fresh(['user:id,name,phone', 'cabang:id,kode_cabang,nama_cabang', 'mesin:id,nama_mesin,serial_number']),
        ]);
    }

    public function correct(Request $request, ImageScan $imageScan)
    {
        $validated = $request->validate([
            'master_token_listrik_id' => ['nullable', 'exists:master_token_listriks,id'],
            'kwh' => ['nullable', 'numeric', 'min:0'],
        ]);

        $analysis = is_array($imageScan->analysis_result) ? $imageScan->analysis_result : [];
        $dataPenting = $analysis['data_penting'] ?? [];

        if (!empty($validated['master_token_listrik_id'])) {
            $token = MasterTokenListrik::findOrFail($validated['master_token_listrik_id']);

            $dataPenting['nomor_meter'] = $token->nomor_meter;
            $dataPenting['master_token_listrik'] = ['id' => $token->id, 'nomor_meter' => $token->nomor_meter];
        }

        $kwhValid = isset($dataPenting['kwh']) && $this->manualUploadService->isValidKwh($dataPenting['kwh']);

        if (!empty($validated['kwh'])) {
            $dataPenting['kwh'] = $this->manualUploadService->normalizeKwh($validated['kwh']);
            $kwhValid = true;
        }

        $analysis['data_penting'] = $dataPenting;

        if (!$kwhValid) {
            $imageScan->update([
                'status' => 'failed',
                'analysis_result' => $analysis,
                'error_message' => 'kWh tidak terbaca jelas.',
            ]);

            return response()->json([
                'status' => 'needs_correction',
                'correction' => 'electricity_kwh',
                'scan_id' => $imageScan->id,
                'nomor_meter' => $dataPenting['nomor_meter'] ?? null,
            ]);
        }

        $imageScan->update([
            'status' => 'success',
            'analysis_result' => $analysis,
            'error_message' => null,
        ]);

        AnalyzeImageJob::dispatch($imageScan->id);

        return response()->json([
            'status' => 'success',
            'scan' => $imageScan->fresh(['user:id,name,phone', 'cabang:id,kode_cabang,nama_cabang', 'mesin:id,nama_mesin,serial_number']),
        ]);
    }

    public function update(Request $request, ImageScan $imageScan)
    {
        $editableKeys = self::EDITABLE_FIELDS[$imageScan->scan_type] ?? [];

        $validated = $request->validate([
            'fields' => ['nullable', 'array'],
            'master_mesin_id' => ['nullable', 'exists:master_mesins,id'],
            'master_token_listrik_id' => ['nullable', 'exists:master_token_listriks,id'],
            'created_at' => ['nullable', 'date'],
        ]);

        $analysis = is_array($imageScan->analysis_result) ? $imageScan->analysis_result : [];
        $dataPenting = $analysis['data_penting'] ?? [];

        foreach ($validated['fields'] ?? [] as $key => $value) {
            if (in_array($key, $editableKeys, true)) {
                $dataPenting[$key] = $value;
            }
        }

        if (isset($dataPenting['kwh'])) {
            $dataPenting['kwh'] = $this->manualUploadService->normalizeKwh($dataPenting['kwh']);
        }

        if (!empty($validated['master_token_listrik_id']) && $imageScan->scan_type === 'electricity') {
            $token = MasterTokenListrik::findOrFail($validated['master_token_listrik_id']);
            $dataPenting['nomor_meter'] = $token->nomor_meter;
            $dataPenting['master_token_listrik'] = ['id' => $token->id, 'nomor_meter' => $token->nomor_meter];
        }

        $relinkedMesin = null;

        if (!empty($validated['master_mesin_id']) && in_array($imageScan->scan_type, self::MACHINE_SCAN_TYPES, true)) {
            $relinkedMesin = MasterMesin::findOrFail($validated['master_mesin_id']);

            $dataPenting['serial_number'] = $relinkedMesin->serial_number;
            $dataPenting['master_mesin'] = [
                'id' => $relinkedMesin->id,
                'nama_mesin' => $relinkedMesin->nama_mesin,
                'serial_number' => $relinkedMesin->serial_number,
            ];

            $imageScan->master_mesin_id = $relinkedMesin->id;
        }

        if ($imageScan->scan_type === 'printer') {
            $dataPenting['total_bw'] = (int) ($dataPenting['bw_a3'] ?? 0) + (int) ($dataPenting['bw_a4'] ?? 0);
            $dataPenting['total_color'] = (int) ($dataPenting['color_a3'] ?? 0) + (int) ($dataPenting['color_a4'] ?? 0);
            $dataPenting['total'] = $dataPenting['total_bw'] + $dataPenting['total_color'];

            $mesinForCalc = $relinkedMesin ?? $imageScan->mesin;

            if ($mesinForCalc) {
                $dataPenting['perhitungan'] = $this->manualUploadService->printerPerhitungan($dataPenting, $mesinForCalc);
            }
        }

        $dataPenting['is_manual_correction'] = true;
        $dataPenting['edited_at'] = now()->toDateTimeString();

        $analysis['data_penting'] = $dataPenting;

        $imageScan->analysis_result = $analysis;
        $imageScan->status = 'success';
        $imageScan->error_message = null;

        if (!empty($validated['created_at'])) {
            $imageScan->created_at = Carbon::parse($validated['created_at']);
        }

        $imageScan->save();

        AnalyzeImageJob::dispatch($imageScan->id);

        return response()->json([
            'status' => 'success',
            'scan' => $imageScan->fresh(['user:id,name,phone', 'cabang:id,kode_cabang,nama_cabang', 'mesin:id,nama_mesin,serial_number']),
        ]);
    }
}
