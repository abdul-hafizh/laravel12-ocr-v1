<?php

namespace App\Http\Controllers;

use App\Models\EmployeeMeasurement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeMeasurementController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeMeasurement::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('employee_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('ket', 'like', "%{$search}%");
            });
        }

        if ($request->filled('periode')) {
            $query->whereDate('periode', $request->periode);
        }

        if ($request->filled('bulan')) {
            $query->whereMonth('periode', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('periode', $request->tahun);
        }

        if ($request->filled('ket')) {
            $query->where('ket', $request->ket);
        }

        $measurements = $query
            ->orderByDesc('periode')
            ->paginate(10)
            ->withQueryString();

        $kets = EmployeeMeasurement::query()
            ->select('ket')
            ->distinct()
            ->whereNotNull('ket')
            ->orderBy('ket')
            ->pluck('ket');

        return Inertia::render('EmployeeMeasurement/Index', [
            'measurements' => $measurements,
            'kets' => $kets,
            'filters' => [
                'search' => $request->search,
                'periode' => $request->periode,
                'bulan' => $request->bulan,
                'tahun' => $request->tahun,
                'ket' => $request->ket,
            ],
        ]);
    }

    public function destroy(EmployeeMeasurement $employeeMeasurement)
    {
        $employeeMeasurement->delete();

        return redirect()
            ->route('employee-measurements.index')
            ->with('message', [
                'text' => 'Data berhasil dihapus!',
                'type' => 'success',
            ]);
    }
}