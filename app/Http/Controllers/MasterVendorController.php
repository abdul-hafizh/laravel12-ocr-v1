<?php

namespace App\Http\Controllers;

use App\Models\MasterVendor;
use App\Mail\VendorMesinMail;
use App\Models\MasterMesin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
            ->with('message', ['text' => 'Master Vendor berhasil Ditambahkan!', 'type' => 'success']);
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
            ->with('message', ['text' => 'Master Vendor berhasil Diperbarui!', 'type' => 'success']);
    }

    public function sendMesinEmail(MasterVendor $masterVendor)
    {
        $emails = collect($masterVendor->email ?? [])
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($emails)) {
            return back()->with('message', [
                'text' => 'Vendor tidak memiliki email.',
                'type' => 'error',
            ]);
        }

        $mesins = MasterMesin::with(['cabang', 'vendor'])
            ->where('master_vendor_id', $masterVendor->id)
            ->where('is_active', true)
            ->orderBy('nama_mesin')
            ->get();

        if ($mesins->isEmpty()) {
            return back()->with('message', [
                'text' => 'Tidak ada mesin aktif untuk vendor ini.',
                'type' => 'error',
            ]);
        }

        Mail::to($emails)->send(new VendorMesinMail($masterVendor, $mesins));

        return back()->with('message', [
            'text' => 'Email daftar mesin berhasil dikirim ke vendor.',
            'type' => 'success',
        ]);
    }

    public function destroy(MasterVendor $masterVendor)
    {
        $masterVendor->delete();

        return redirect()
            ->route('master-vendor.index')
            ->with('message', ['text' => 'Master Vendor berhasil Dihapus!', 'type' => 'success']);
    }
}
