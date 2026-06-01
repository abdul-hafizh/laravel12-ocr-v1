<?php

namespace App\Http\Controllers;

use App\Models\MasterCabang;
use App\Models\MasterMesin;
use App\Models\MasterVendor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterMesinController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterMesin::with([
            'cabang',
            'vendor',
            'maintenanceParts',
        ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('nama_mesin', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%")
                    ->orWhere('tipe', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                        $vendorQuery->where('nama_vendor', 'like', "%{$search}%")
                            ->orWhere('kode_vendor', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('master_cabang_id')) {
            $query->where('master_cabang_id', $request->master_cabang_id);
        }

        if ($request->filled('master_vendor_id')) {
            $query->where('master_vendor_id', $request->master_vendor_id);
        }

        return Inertia::render('MasterMesin/Index', [
            'mesins' => $query->orderBy('nama_mesin')->paginate(10)->withQueryString(),

            'cabangs' => MasterCabang::where('is_active', true)
                ->orderBy('nama_cabang')
                ->get(),

            'vendors' => MasterVendor::where('is_active', true)
                ->orderBy('nama_vendor')
                ->get(),

            'filters' => $request->only([
                'search',
                'master_cabang_id',
                'master_vendor_id',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateData($request);

        $parts = $validated['maintenance_parts'] ?? [];
        unset($validated['maintenance_parts']);

        $mesin = MasterMesin::create($validated);

        foreach ($parts as $part) {
            if (!empty($part['nama_part'])) {
                $mesin->maintenanceParts()->create([
                    'nama_part' => $part['nama_part'],
                    'harga_part' => $part['harga_part'] ?? 0,
                ]);
            }
        }

        return back()->with('message', ['text' => 'Master Mesin berhasil Ditambahkan!', 'type' => 'success']);
    }

    public function update(Request $request, MasterMesin $masterMesin)
    {
        $validated = $this->validateData($request, $masterMesin->id);

        $parts = $validated['maintenance_parts'] ?? [];
        unset($validated['maintenance_parts']);

        $masterMesin->update($validated);

        $masterMesin->maintenanceParts()->delete();

        foreach ($parts as $part) {
            if (!empty($part['nama_part'])) {
                $masterMesin->maintenanceParts()->create([
                    'nama_part' => $part['nama_part'],
                    'harga_part' => $part['harga_part'] ?? 0,
                ]);
            }
        }

        return back()->with('message', ['text' => 'Master Mesin berhasil Diperbarui!', 'type' => 'success']);
    }

    public function destroy(MasterMesin $masterMesin)
    {
        $masterMesin->delete();

        return back()->with('message', ['text' => 'Master Mesin berhasil Dihapus!', 'type' => 'success']);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'master_vendor_id' => ['nullable', 'exists:master_vendors,id'],

            'nama_mesin' => ['required', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],

            'serial_number' => [
                'required',
                'string',
                'max:255',
                'unique:master_mesins,serial_number' . ($ignoreId ? ',' . $ignoreId : ''),
            ],

            'harga_minimum' => ['required', 'numeric', 'min:0'],
            'harga_maksimum' => ['required', 'numeric', 'min:0'],

            'harga_bw' => ['required', 'numeric', 'min:0'],
            'harga_color' => ['required', 'numeric', 'min:0'],
            'harga_long_sheet' => ['required', 'numeric', 'min:0'],

            'harga_color_a3' => ['nullable', 'numeric', 'min:0'],
            'harga_color_a4' => ['nullable', 'numeric', 'min:0'],
            'harga_bw_a3' => ['nullable', 'numeric', 'min:0'],
            'harga_bw_a4' => ['nullable', 'numeric', 'min:0'],

            'free_klik_percent' => ['nullable', 'numeric', 'min:0'],
            'minimum_charge' => ['nullable', 'numeric', 'min:0'],
            'minimum_charge_type' => ['nullable', 'string', 'max:255'],
            'harga_setelah_minimum_charge' => ['nullable', 'numeric', 'min:0'],
            'status_kepemilikan' => ['nullable', 'string', 'max:255'],

            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],

            'maintenance_parts' => ['nullable', 'array'],
            'maintenance_parts.*.nama_part' => ['nullable', 'string', 'max:255'],
            'maintenance_parts.*.harga_part' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}