<?php

namespace App\Http\Controllers;

use App\Models\MasterVendor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterVendorController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterVendor::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('kode_vendor', 'like', "%{$search}%")
                    ->orWhere('nama_vendor', 'like', "%{$search}%")
                    ->orWhere('pic', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%")
                    ->orWhereJsonContains('email', $search);
            });
        }

        $vendors = $query
            ->orderBy('nama_vendor')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('MasterVendor/Index', [
            'vendors' => $vendors,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_vendor' => ['required', 'string', 'max:100', 'unique:master_vendors,kode_vendor'],
            'nama_vendor' => ['required', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:50'],

            'email' => ['nullable', 'array'],
            'email.*' => ['nullable', 'email', 'max:255'],

            'alamat' => ['nullable', 'string'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['email'] = array_values(
            array_filter($validated['email'] ?? [])
        );

        MasterVendor::create($validated);

        return redirect()
            ->route('master-vendor.index')
            ->with('success', 'Master vendor berhasil ditambahkan.');
    }

    public function update(Request $request, MasterVendor $masterVendor)
    {
        $validated = $request->validate([
            'kode_vendor' => ['required', 'string', 'max:100', 'unique:master_vendors,kode_vendor,' . $masterVendor->id],
            'nama_vendor' => ['required', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:50'],

            'email' => ['nullable', 'array'],
            'email.*' => ['nullable', 'email', 'max:255'],

            'alamat' => ['nullable', 'string'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['email'] = array_values(
            array_filter($validated['email'] ?? [])
        );

        $masterVendor->update($validated);

        return redirect()
            ->route('master-vendor.index')
            ->with('success', 'Master vendor berhasil diperbarui.');
    }

    public function destroy(MasterVendor $masterVendor)
    {
        $masterVendor->delete();

        return redirect()
            ->route('master-vendor.index')
            ->with('success', 'Master vendor berhasil dihapus.');
    }
}