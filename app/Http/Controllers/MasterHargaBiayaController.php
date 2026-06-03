<?php

namespace App\Http\Controllers;

use App\Models\MasterHargaBiaya;
use App\Models\MasterCabang;
use App\Models\MasterVendor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterHargaBiayaController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterHargaBiaya::with(['cabang', 'vendor']);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('kategori_biaya', 'like', "%{$search}%")
                    ->orWhere('nama_biaya', 'like', "%{$search}%")
                    ->orWhere('tipe_harga', 'like', "%{$search}%")
                    ->orWhere('kode_coa', 'like', "%{$search}%")
                    ->orWhere('nama_coa', 'like', "%{$search}%");
            });
        }

        if ($request->filled('master_cabang_id')) {
            $query->where('master_cabang_id', $request->master_cabang_id);
        }

        if ($request->filled('kategori_biaya')) {
            $query->where('kategori_biaya', $request->kategori_biaya);
        }

        if ($request->filled('is_coa')) {
            $query->where('is_coa', $request->boolean('is_coa'));
        }

        return Inertia::render('MasterHargaBiaya/Index', [
            'hargaBiayas' => $query->orderBy('kategori_biaya')->orderBy('nama_biaya')->paginate(10)->withQueryString(),
            'cabangs' => MasterCabang::where('is_active', true)->orderBy('nama_cabang')->get(),
            'vendors' => MasterVendor::where('is_active', true)->orderBy('nama_vendor')->get(),
            'filters' => $request->only(['search', 'master_cabang_id', 'kategori_biaya', 'is_coa']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['nullable', 'exists:master_cabangs,id'],
            'master_vendor_id' => ['nullable', 'exists:master_vendors,id'],
            'kategori_biaya' => ['required', 'string', 'max:100'],
            'nama_biaya' => ['required', 'string', 'max:255'],
            'tipe_harga' => ['nullable', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:100'],
            'is_coa' => ['boolean'],
            'kode_coa' => ['nullable', 'string', 'max:100'],
            'nama_coa' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterHargaBiaya::create($validated);

        return back()->with('message', ['text' => 'Master Harga/Biaya berhasil ditambahkan!', 'type' => 'success']);
    }

    public function update(Request $request, MasterHargaBiaya $masterHargaBiaya)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['nullable', 'exists:master_cabangs,id'],
            'master_vendor_id' => ['nullable', 'exists:master_vendors,id'],
            'kategori_biaya' => ['required', 'string', 'max:100'],
            'nama_biaya' => ['required', 'string', 'max:255'],
            'tipe_harga' => ['nullable', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:100'],
            'is_coa' => ['boolean'],
            'kode_coa' => ['nullable', 'string', 'max:100'],
            'nama_coa' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterHargaBiaya->update($validated);

        return back()->with('message', ['text' => 'Master Harga/Biaya berhasil diperbarui!', 'type' => 'success']);
    }

    public function destroy(MasterHargaBiaya $masterHargaBiaya)
    {
        $masterHargaBiaya->delete();

        return back()->with('message', ['text' => 'Master Harga/Biaya berhasil dihapus!', 'type' => 'success']);
    }
}