<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageScan;
use Illuminate\Http\Request;

class ImageScanController extends Controller
{
    public function index(Request $request)
    {
        $query = ImageScan::with([
            'user:id,name,email,phone,employee_id',
            'cabang:id,kode_cabang,nama_cabang,alamat',
        ]);

        if ($request->filled('scan_type')) {
            $query->where('scan_type', $request->scan_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $scans = $query
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $scans,
        ]);
    }

    public function show($id)
    {
        $scan = ImageScan::with([
            'user:id,name,email,phone,employee_id',
            'cabang:id,kode_cabang,nama_cabang,alamat',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $scan,
        ]);
    }
}