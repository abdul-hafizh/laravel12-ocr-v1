<?php

namespace App\Http\Controllers;

use App\Models\MasterDayaListrik;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterDayaListrikController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterDayaListrik::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('daya', 'like', "%{$search}%")
                    ->orWhere('harga_per_kwh', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        return Inertia::render('MasterDayaListrik/Index', [
            'dayaListriks' => $query
                ->orderBy('daya')
                ->paginate(10)
                ->withQueryString(),

            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'daya' => ['required', 'string', 'max:100', 'unique:master_daya_listriks,daya'],
            'harga_per_kwh' => ['required', 'numeric', 'min:0'],
            'ppn_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterDayaListrik::create($validated);

        return back()->with('message', [
            'text' => 'Master Daya Listrik berhasil ditambahkan!',
            'type' => 'success',
        ]);
    }

    public function update(Request $request, MasterDayaListrik $masterDayaListrik)
    {
        $validated = $request->validate([
            'daya' => [
                'required',
                'string',
                'max:100',
                'unique:master_daya_listriks,daya,' . $masterDayaListrik->id,
            ],
            'harga_per_kwh' => ['required', 'numeric', 'min:0'],
            'ppn_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterDayaListrik->update($validated);

        return back()->with('message', [
            'text' => 'Master Daya Listrik berhasil diperbarui!',
            'type' => 'success',
        ]);
    }

    public function destroy(MasterDayaListrik $masterDayaListrik)
    {
        $masterDayaListrik->delete();

        return back()->with('message', [
            'text' => 'Master Daya Listrik berhasil dihapus!',
            'type' => 'success',
        ]);
    }
}