<?php

namespace App\Http\Controllers;

use App\Models\MasterTokenListrik;
use App\Models\MasterCabang;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterTokenListrikController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterTokenListrik::with('cabang');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('nomor_meter', 'like', "%{$search}%")
                    ->orWhere('nama_pelanggan', 'like', "%{$search}%")
                    ->orWhere('daya', 'like', "%{$search}%");
            });
        }

        if ($request->filled('master_cabang_id')) {
            $query->where('master_cabang_id', $request->master_cabang_id);
        }

        return Inertia::render('MasterTokenListrik/Index', [
            'tokenListriks' => $query->orderBy('nomor_meter')->paginate(10)->withQueryString(),
            'cabangs' => MasterCabang::where('is_active', true)->orderBy('nama_cabang')->get(),
            'filters' => $request->only(['search', 'master_cabang_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'nomor_meter' => ['required', 'string', 'max:100', 'unique:master_token_listriks,nomor_meter'],
            'nama_pelanggan' => ['nullable', 'string', 'max:255'],
            'daya' => ['nullable', 'string', 'max:100'],
            'nominal_default' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterTokenListrik::create($validated);

        return back()->with('success', 'Master token listrik berhasil ditambahkan.');
    }

    public function update(Request $request, MasterTokenListrik $masterTokenListrik)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'nomor_meter' => ['required', 'string', 'max:100', 'unique:master_token_listriks,nomor_meter,' . $masterTokenListrik->id],
            'nama_pelanggan' => ['nullable', 'string', 'max:255'],
            'daya' => ['nullable', 'string', 'max:100'],
            'nominal_default' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterTokenListrik->update($validated);

        return back()->with('success', 'Master token listrik berhasil diperbarui.');
    }

    public function destroy(MasterTokenListrik $masterTokenListrik)
    {
        $masterTokenListrik->delete();

        return back()->with('success', 'Master token listrik berhasil dihapus.');
    }
}