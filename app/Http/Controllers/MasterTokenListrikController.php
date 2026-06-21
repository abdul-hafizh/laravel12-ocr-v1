<?php

namespace App\Http\Controllers;

use App\Models\MasterTokenListrik;
use App\Models\MasterCabang;
use App\Models\MasterDayaListrik;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterTokenListrikController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterTokenListrik::query()
            ->select('master_token_listriks.*')
            ->with(['cabang', 'dayaListrik'])
            ->leftJoin('master_cabangs', 'master_token_listriks.master_cabang_id', '=', 'master_cabangs.id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('master_token_listriks.nomor_meter', 'like', "%{$search}%")
                    ->orWhere('master_token_listriks.nama_pelanggan', 'like', "%{$search}%")
                    ->orWhereHas('dayaListrik', function ($dq) use ($search) {
                        $dq->where('daya', 'like', "%{$search}%");
                    })
                    ->orWhere('master_cabangs.nama_cabang', 'like', "%{$search}%");
            });
        }

        if ($request->filled('master_cabang_id')) {
            $query->where('master_token_listriks.master_cabang_id', $request->master_cabang_id);
        }

        return Inertia::render('MasterTokenListrik/Index', [
            'tokenListriks' => $query
                ->orderBy('master_cabangs.nama_cabang')
                ->orderBy('master_token_listriks.nomor_meter')
                ->paginate(10)
                ->withQueryString(),

            'cabangs' => MasterCabang::where('is_active', true)
                ->orderBy('nama_cabang')
                ->get(),

            'dayaListriks' => MasterDayaListrik::where('is_active', true)
                ->orderBy('daya')
                ->get(),

            'filters' => $request->only(['search', 'master_cabang_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'master_daya_listrik_id' => ['required', 'exists:master_daya_listriks,id'],
            'nomor_meter' => ['required', 'string', 'max:100', 'unique:master_token_listriks,nomor_meter'],
            'nama_pelanggan' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        MasterTokenListrik::create($validated);

        return back()->with('message', ['text' => 'Master Token Listrik berhasil ditambahkan!', 'type' => 'success']);
    }

    public function update(Request $request, MasterTokenListrik $masterTokenListrik)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'master_daya_listrik_id' => ['required', 'exists:master_daya_listriks,id'],
            'nomor_meter' => ['required', 'string', 'max:100', 'unique:master_token_listriks,nomor_meter'],
            'nama_pelanggan' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $masterTokenListrik->update($validated);

        return back()->with('message', ['text' => 'Master Token Listrik berhasil diperbarui!', 'type' => 'success']);
    }

    public function destroy(MasterTokenListrik $masterTokenListrik)
    {
        $masterTokenListrik->delete();

        return back()->with('message', ['text' => 'Master Token Listrik berhasil dihapus!', 'type' => 'success']);
    }
}