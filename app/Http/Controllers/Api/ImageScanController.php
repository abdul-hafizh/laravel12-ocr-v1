<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImageScanController extends Controller
{
    public function index(Request $request)
    {
        $query = ImageScan::query()
            ->with([
                'user:id,name,email,phone,employee_id',
                'cabang:id,kode_cabang,nama_cabang,alamat',
                'mesin:id,nama_mesin,serial_number',
            ])
            ->leftJoin('dbo.machine_maintenance_costs as mmc', 'mmc.image_scan_id', '=', 'image_scans.id')
            ->select([
                'image_scans.*',
                'mmc.cost_type',
                'mmc.nama_part',
                'mmc.nominal',
                'mmc.keterangan as cost_keterangan',
                'mmc.periode_start',
                'mmc.periode_end',
            ]);

        if ($request->filled('scan_type')) {
            $query->where('image_scans.scan_type', $request->scan_type);
        }

        if ($request->filled('user_id')) {
            $query->where('image_scans.user_id', $request->user_id);
        }

        if ($request->filled('cabang_id')) {
            $query->where('image_scans.cabang_id', $request->cabang_id);
        }

        if ($request->filled('status')) {
            $query->where('image_scans.status', $request->status);
        }

        $scans = $query
            ->orderByDesc('image_scans.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $scans,
        ]);
    }

    public function show($id)
    {
        $scan = ImageScan::query()
            ->with([
                'user:id,name,email,phone,employee_id',
                'cabang:id,kode_cabang,nama_cabang,alamat',
                'cabang.users:id,name,email,phone,employee_id',
                'mesin:id,nama_mesin,serial_number',
            ])
            ->leftJoin('dbo.machine_maintenance_costs as mmc', 'mmc.image_scan_id', '=', 'image_scans.id')
            ->select([
                'image_scans.*',
                'mmc.cost_type',
                'mmc.nama_part',
                'mmc.nominal',
                'mmc.keterangan as cost_keterangan',
                'mmc.periode_start',
                'mmc.periode_end',
            ])
            ->where('image_scans.id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $scan,
        ]);
    }
}