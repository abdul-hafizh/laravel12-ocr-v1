<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'electricity' => $this->getDataByType('electricity', $filters, $perPage, 'electricity_page'),
                'online_receipt' => $this->getDataByType('online_receipt', $filters, $perPage, 'online_receipt_page'),
                'printer' => $this->getDataByType('printer', $filters, $perPage, 'printer_page'),
            ],
        ]);
    }

    private function baseQuery(array $filters)
    {
        $query = DB::table('dbo.v_image_scan_results');

        if (!empty($filters['tanggal'])) {
            $query->whereDay('created_at', $filters['tanggal']);
        }

        if (!empty($filters['bulan'])) {
            $query->whereMonth('created_at', $filters['bulan']);
        }

        if (!empty($filters['tahun'])) {
            $query->whereYear('created_at', $filters['tahun']);
        }

        if (!empty($filters['cabang'])) {
            $query->where('nama_cabang', $filters['cabang']);
        }

        if (!empty($filters['user_phone'])) {
            $query->where('user_phone', $filters['user_phone']);
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
        return [
            'total' => (clone $this->baseQuery($filters))->count(),
            'electricity' => (clone $this->baseQuery($filters))->where('scan_type', 'electricity')->count(),
            'online_receipt' => (clone $this->baseQuery($filters))->where('scan_type', 'online_receipt')->count(),
            'printer' => (clone $this->baseQuery($filters))->where('scan_type', 'printer')->count(),
        ];
    }

    private function getCabangs()
    {
        return DB::table('dbo.v_image_scan_results')
            ->whereNotNull('nama_cabang')
            ->select('nama_cabang')
            ->distinct()
            ->orderBy('nama_cabang')
            ->pluck('nama_cabang');
    }

    private function getUserPhones()
    {
        return DB::table('dbo.v_image_scan_results')
            ->whereNotNull('user_phone')
            ->select('user_phone')
            ->distinct()
            ->orderBy('user_phone')
            ->pluck('user_phone');
    }
}