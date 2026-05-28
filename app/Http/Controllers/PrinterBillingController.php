<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PrinterBillingController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('dbo.v_printer_meter_billing');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('nama_mesin_scan', 'like', "%{$search}%")
                    ->orWhere('nama_mesin_master', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('kode_vendor', 'like', "%{$search}%")
                    ->orWhere('nama_cabang', 'like', "%{$search}%")
                    ->orWhere('kode_cabang', 'like', "%{$search}%");
            });
        }

        if ($request->filled('vendor_name')) {
            $query->where('vendor_name', $request->vendor_name);
        }

        if ($request->filled('billing_status')) {
            $query->where('billing_status', $request->billing_status);
        }

        if ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        return Inertia::render('PrinterBilling/Index', [
            'billings' => $query
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString(),

            'vendors' => DB::table('dbo.master_vendors')
                ->where('is_active', true)
                ->orderBy('nama_vendor')
                ->get([
                    'id',
                    'kode_vendor',
                    'nama_vendor',
                ]),

            'cabangs' => DB::table('dbo.master_cabangs')
                ->where('is_active', true)
                ->orderBy('nama_cabang')
                ->get([
                    'id',
                    'kode_cabang',
                    'nama_cabang',
                ]),

            'filters' => $request->only([
                'search',
                'vendor_name',
                'billing_status',
                'cabang_id',
            ]),
        ]);
    }
}