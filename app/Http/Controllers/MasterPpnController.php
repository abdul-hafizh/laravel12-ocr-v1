<?php

namespace App\Http\Controllers;

use App\Models\MasterPpn;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterPpnController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterPpn::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('nama_pajak', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        return Inertia::render('MasterPpn/Index', [
            'ppns' => $query
                ->orderBy('nama_pajak')
                ->paginate(10)
                ->withQueryString(),

            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_pajak' => ['required', 'string', 'max:100', 'unique:master_ppns,nama_pajak'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterPpn::create($validated);

        return back()->with('message', [
            'text' => 'Master PPN berhasil ditambahkan!',
            'type' => 'success',
        ]);
    }

    public function update(Request $request, MasterPpn $masterPpn)
    {
        $validated = $request->validate([
            'nama_pajak' => [
                'required',
                'string',
                'max:100',
                'unique:master_ppns,nama_pajak,' . $masterPpn->id,
            ],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterPpn->update($validated);

        return back()->with('message', [
            'text' => 'Master PPN berhasil diperbarui!',
            'type' => 'success',
        ]);
    }

    public function destroy(MasterPpn $masterPpn)
    {
        $masterPpn->delete();

        return back()->with('message', [
            'text' => 'Master PPN berhasil dihapus!',
            'type' => 'success',
        ]);
    }
}
