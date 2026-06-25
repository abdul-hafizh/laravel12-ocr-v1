<?php

namespace App\Http\Controllers;

use App\Models\MasterCabang;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MasterCabangController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterCabang::with('users');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('kode_cabang', 'like', "%{$search}%")
                    ->orWhere('nama_cabang', 'like', "%{$search}%")
                    ->orWhereHas('users', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $data = $query
            ->orderBy('nama_cabang')
            ->paginate(10)
            ->withQueryString();

        $users = User::query()
            ->where('is_active', true)
            ->where('is_delete', false)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'phone',
                'employee_id',
            ]);

        return Inertia::render('MasterCabang/Index', [
            'cabangs' => $data,
            'users' => $users,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_cabang' => [
                'required',
                'string',
                'max:100',
                'unique:master_cabangs,kode_cabang',
            ],
            'nama_cabang' => ['required', 'string', 'max:255'],
            'nama_pt' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $userIds = $validated['user_ids'] ?? [];
        unset($validated['user_ids']);

        $cabang = MasterCabang::create($validated);

        $cabang->users()->sync($userIds);

        return redirect()
            ->route('master-cabang.index')
            ->with('message', ['text' => 'Data berhasil disimpan!', 'type' => 'success']);
    }

    public function update(Request $request, MasterCabang $masterCabang)
    {
        $validated = $request->validate([
            'kode_cabang' => [
                'required',
                'string',
                'max:100',
                'unique:master_cabangs,kode_cabang,' . $masterCabang->id,
            ],
            'nama_cabang' => ['required', 'string', 'max:255'],
            'nama_pt' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $userIds = $validated['user_ids'] ?? [];
        unset($validated['user_ids']);

        $masterCabang->update($validated);

        $masterCabang->users()->sync($userIds);

        return redirect()
            ->route('master-cabang.index')
            ->with('message', ['text' => 'Data berhasil diperbaharui!', 'type' => 'success']);
    }

    public function destroy(MasterCabang $masterCabang)
    {
        $masterCabang->delete();

        return redirect()
            ->route('master-cabang.index')
            ->with('message', ['text' => 'Data berhasil Dihapus!', 'type' => 'success']);
    }
}
