<?php

namespace App\Http\Controllers\Api;

use App\Helpers\UserAccessHelper;
use App\Http\Controllers\Controller;
use App\Models\ImageScan;
use Illuminate\Http\Request;

class ImageScanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

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

        UserAccessHelper::applyCabangFilter($query, $user, 'image_scans.cabang_id');

        if ($request->filled('scan_type')) {
            $query->where('image_scans.scan_type', $request->scan_type);
        }

        if ($request->filled('user_id')) {
            $query->where('image_scans.user_id', $request->user_id);
        }

        if ($request->filled('cabang_id')) {
            $allowedCabangIds = UserAccessHelper::cabangIds($user);

            if ($allowedCabangIds->contains((int) $request->cabang_id)) {
                $query->where('image_scans.cabang_id', $request->cabang_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('status')) {
            $query->where('image_scans.status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('image_scans.id', 'like', "%{$search}%")
                    ->orWhere('image_scans.extracted_text', 'like', "%{$search}%")
                    ->orWhere('image_scans.error_message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('cabang', function ($c) use ($search) {
                        $c->where('nama_cabang', 'like', "%{$search}%")
                            ->orWhere('kode_cabang', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('image_scans.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('image_scans.created_at', '<=', $request->date_to);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = min(max($perPage, 5), 100);

        $scans = $query
            ->orderByDesc('image_scans.created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $scans->items(),
            'meta' => [
                'current_page' => $scans->currentPage(),
                'last_page' => $scans->lastPage(),
                'per_page' => $scans->perPage(),
                'total' => $scans->total(),
                'from' => $scans->firstItem(),
                'to' => $scans->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $query = ImageScan::query()
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
            ->where('image_scans.id', $id);

        UserAccessHelper::applyCabangFilter($query, $user, 'image_scans.cabang_id');

        $scan = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $scan,
        ]);
    }
}