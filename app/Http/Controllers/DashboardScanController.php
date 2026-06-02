<?php

namespace App\Http\Controllers;

use App\Models\ImageScan;
use App\Models\MasterCabang;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardScanController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $filters = [
            'tanggal' => $request->tanggal,
            'bulan' => $request->bulan,
            'tahun' => $request->tahun,
            'cabang' => $request->cabang,
            'user_phone' => $request->user_phone,
        ];

        return response()->json([
            'success' => true,

            'filters' => [
                'cabangs' => $this->getCabangs(),
                'user_phones' => $this->getUserPhones(),
            ],

            'summary' => $this->getSummary($filters),

            'data' => [
                'electricity' => $this->getDataByType(
                    'electricity',
                    $filters,
                    $perPage,
                    'electricity_page'
                ),

                'online_receipt' => $this->getDataByType(
                    'online_receipt',
                    $filters,
                    $perPage,
                    'online_receipt_page'
                ),

                'printer' => $this->getDataByType(
                    'printer',
                    $filters,
                    $perPage,
                    'printer_page'
                ),
            ],
        ]);
    }

    private function baseQuery(array $filters)
    {
        $query = ImageScan::query()
            ->with([
                'user:id,name,email,phone,role_id',
                'user.role:id,name,slug',
                'cabang:id,kode_cabang,nama_cabang',
            ]);

        if (!empty($filters['tanggal'])) {
            $query->whereDay('created_at', (int) $filters['tanggal']);
        }

        if (!empty($filters['bulan'])) {
            $query->whereMonth('created_at', (int) $filters['bulan']);
        }

        if (!empty($filters['tahun'])) {
            $query->whereYear('created_at', (int) $filters['tahun']);
        }

        if (!empty($filters['cabang'])) {
            $query->where('cabang_id', $filters['cabang']);
        }

        if (!empty($filters['user_phone'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('phone', $filters['user_phone']);
            });
        }

        return $query;
    }

    private function getDataByType(string $scanType, array $filters, int $perPage, string $pageName)
    {
        return $this->baseQuery($filters)
            ->where('scan_type', $scanType)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    private function getSummary(array $filters)
    {
        $baseQuery = $this->baseQuery($filters);

        return [
            'total' => (clone $baseQuery)->count(),

            'electricity' => (clone $baseQuery)
                ->where('scan_type', 'electricity')
                ->count(),

            'online_receipt' => (clone $baseQuery)
                ->where('scan_type', 'online_receipt')
                ->count(),

            'printer' => (clone $baseQuery)
                ->where('scan_type', 'printer')
                ->count(),
        ];
    }

    private function getCabangs()
    {
        return MasterCabang::query()
            ->where('is_active', true)
            ->orderBy('nama_cabang')
            ->get([
                'id',
                'kode_cabang',
                'nama_cabang',
            ]);
    }

    private function getUserPhones()
    {
        return User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('is_active', true)
            ->where('is_delete', false)
            ->orderBy('phone')
            ->pluck('phone');
    }
}