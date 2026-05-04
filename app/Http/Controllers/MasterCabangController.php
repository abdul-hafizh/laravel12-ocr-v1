<?php

namespace App\Http\Controllers;

use App\Models\MasterCabang;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterCabangController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterCabang::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('kode_cabang', 'like', "%{$search}%")
                  ->orWhere('nama_cabang', 'like', "%{$search}%")
                  ->orWhere('pic', 'like', "%{$search}%")
                  ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        $data = $query
            ->orderBy('nama_cabang')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('MasterCabang/Index', [
            'cabangs' => $data,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_cabang' => ['required', 'string', 'max:100', 'unique:master_cabangs,kode_cabang'],
            'nama_cabang' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterCabang::create($validated);

        return redirect()
            ->route('master-cabang.index')
            ->with('success', 'Master cabang berhasil ditambahkan.');
    }

    public function update(Request $request, MasterCabang $masterCabang)
    {
        $validated = $request->validate([
            'kode_cabang' => ['required', 'string', 'max:100', 'unique:master_cabangs,kode_cabang,' . $masterCabang->id],
            'nama_cabang' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterCabang->update($validated);

        return redirect()
            ->route('master-cabang.index')
            ->with('success', 'Master cabang berhasil diperbarui.');
    }

    public function destroy(MasterCabang $masterCabang)
    {
        $masterCabang->delete();

        return redirect()
            ->route('master-cabang.index')
            ->with('success', 'Master cabang berhasil dihapus.');
    }
}