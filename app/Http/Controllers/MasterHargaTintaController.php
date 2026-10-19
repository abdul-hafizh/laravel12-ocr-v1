<?php

namespace App\Http\Controllers;

use App\Models\MasterHargaTinta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MasterHargaTintaController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterHargaTinta::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('nama_tinta', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        return Inertia::render('MasterHargaTinta/Index', [
            'tintas' => $query
                ->orderBy('nama_tinta')
                ->paginate(10)
                ->withQueryString(),

            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        MasterHargaTinta::create($this->validated($request));

        return back()->with('message', [
            'text' => 'Master harga tinta berhasil ditambahkan!',
            'type' => 'success',
        ]);
    }

    public function update(Request $request, MasterHargaTinta $masterHargaTinta)
    {
        $masterHargaTinta->update($this->validated($request));

        return back()->with('message', [
            'text' => 'Master harga tinta berhasil diperbarui!',
            'type' => 'success',
        ]);
    }

    public function destroy(MasterHargaTinta $masterHargaTinta)
    {
        $masterHargaTinta->delete();

        return back()->with('message', [
            'text' => 'Master harga tinta berhasil dihapus!',
            'type' => 'success',
        ]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'nama_tinta' => ['required', 'string', 'max:150'],
            'status_kepemilikan' => ['nullable', Rule::in(['milik', 'sewa'])],
            'harga' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['status_kepemilikan'] = $validated['status_kepemilikan'] ?: null;

        return $validated;
    }
}
