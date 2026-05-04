<?php

namespace App\Http\Controllers;

use App\Models\MasterMesin;
use App\Models\MasterCabang;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterMesinController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterMesin::with('cabang');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('kode_mesin', 'like', "%{$search}%")
                    ->orWhere('nama_mesin', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%")
                    ->orWhere('tipe', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('master_cabang_id')) {
            $query->where('master_cabang_id', $request->master_cabang_id);
        }

        return Inertia::render('MasterMesin/Index', [
            'mesins' => $query->orderBy('nama_mesin')->paginate(10)->withQueryString(),
            'cabangs' => MasterCabang::where('is_active', true)->orderBy('nama_cabang')->get(),
            'filters' => $request->only(['search', 'master_cabang_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'kode_mesin' => ['required', 'string', 'max:100', 'unique:master_mesins,kode_mesin'],
            'nama_mesin' => ['required', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'harga_minimum' => ['required', 'numeric', 'min:0'],
            'harga_normal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterMesin::create($validated);

        return back()->with('success', 'Master mesin berhasil ditambahkan.');
    }

    public function update(Request $request, MasterMesin $masterMesin)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'kode_mesin' => ['required', 'string', 'max:100', 'unique:master_mesins,kode_mesin,' . $masterMesin->id],
            'nama_mesin' => ['required', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'harga_minimum' => ['required', 'numeric', 'min:0'],
            'harga_normal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterMesin->update($validated);

        return back()->with('success', 'Master mesin berhasil diperbarui.');
    }

    public function destroy(MasterMesin $masterMesin)
    {
        $masterMesin->delete();

        return back()->with('success', 'Master mesin berhasil dihapus.');
    }
}