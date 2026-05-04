<?php

namespace App\Http\Controllers;

use App\Models\MasterSkpd;
use App\Models\MasterKendaraan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterSkpdController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterSkpd::with('kendaraan');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('nomor_skpd', 'like', "%{$search}%")
                    ->orWhere('nama_pemilik', 'like', "%{$search}%")
                    ->orWhere('nomor_polisi', 'like', "%{$search}%");
            });
        }

        return Inertia::render('MasterSkpd/Index', [
            'skpds' => $query->orderBy('tanggal_jatuh_tempo')->paginate(10)->withQueryString(),
            'kendaraans' => MasterKendaraan::where('is_active', true)->orderBy('nomor_polisi')->get(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_kendaraan_id' => ['nullable', 'exists:master_kendaraans,id'],
            'nomor_skpd' => ['required', 'string', 'max:100', 'unique:master_skpds,nomor_skpd'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'nomor_polisi' => ['nullable', 'string', 'max:50'],
            'nominal_pajak' => ['required', 'numeric', 'min:0'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterSkpd::create($validated);

        return back()->with('success', 'Master SKPD berhasil ditambahkan.');
    }

    public function update(Request $request, MasterSkpd $masterSkpd)
    {
        $validated = $request->validate([
            'master_kendaraan_id' => ['nullable', 'exists:master_kendaraans,id'],
            'nomor_skpd' => ['required', 'string', 'max:100', 'unique:master_skpds,nomor_skpd,' . $masterSkpd->id],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'nomor_polisi' => ['nullable', 'string', 'max:50'],
            'nominal_pajak' => ['required', 'numeric', 'min:0'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterSkpd->update($validated);

        return back()->with('success', 'Master SKPD berhasil diperbarui.');
    }

    public function destroy(MasterSkpd $masterSkpd)
    {
        $masterSkpd->delete();

        return back()->with('success', 'Master SKPD berhasil dihapus.');
    }
}