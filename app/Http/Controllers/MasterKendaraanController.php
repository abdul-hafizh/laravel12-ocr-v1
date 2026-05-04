<?php

namespace App\Http\Controllers;

use App\Models\MasterKendaraan;
use App\Models\MasterCabang;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterKendaraanController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterKendaraan::with('cabang');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('nomor_polisi', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%")
                    ->orWhere('tipe', 'like', "%{$search}%")
                    ->orWhere('nama_pemilik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('master_cabang_id')) {
            $query->where('master_cabang_id', $request->master_cabang_id);
        }

        return Inertia::render('MasterKendaraan/Index', [
            'kendaraans' => $query->orderBy('nomor_polisi')->paginate(10)->withQueryString(),
            'cabangs' => MasterCabang::where('is_active', true)->orderBy('nama_cabang')->get(),
            'filters' => $request->only(['search', 'master_cabang_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['nullable', 'exists:master_cabangs,id'],
            'jenis_kendaraan' => ['required', 'string', 'max:100'],
            'nomor_polisi' => ['required', 'string', 'max:50', 'unique:master_kendaraans,nomor_polisi'],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterKendaraan::create($validated);

        return back()->with('success', 'Master kendaraan berhasil ditambahkan.');
    }

    public function update(Request $request, MasterKendaraan $masterKendaraan)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['nullable', 'exists:master_cabangs,id'],
            'jenis_kendaraan' => ['required', 'string', 'max:100'],
            'nomor_polisi' => ['required', 'string', 'max:50', 'unique:master_kendaraans,nomor_polisi,' . $masterKendaraan->id],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterKendaraan->update($validated);

        return back()->with('success', 'Master kendaraan berhasil diperbarui.');
    }

    public function destroy(MasterKendaraan $masterKendaraan)
    {
        $masterKendaraan->delete();

        return back()->with('success', 'Master kendaraan berhasil dihapus.');
    }
}