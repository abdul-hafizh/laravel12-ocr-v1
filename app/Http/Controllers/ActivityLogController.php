<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public const MODULE_LABELS = [
        'electricity' => 'Token Listrik',
        'printer' => 'Samafitro',
        'astra' => 'ASTRA Mesin',
        'cea' => 'CEA Mesin',
        'asaba' => 'ASABA Mesin',
        'part_maintenance' => 'Part Maintenance',
        'online_receipt' => 'Bukti Bayar',
    ];

    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'like', 'hasil_upload.%');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module')) {
            $query->where('module', 'hasil_upload.' . $request->module);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $logs->getCollection()->transform(function (ActivityLog $log) {
            $key = str_replace('hasil_upload.', '', $log->module);
            $log->module_label = self::MODULE_LABELS[$key] ?? $key;

            return $log;
        });

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'modules' => collect(self::MODULE_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'filters' => [
                'search' => $request->search,
                'module' => $request->module,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
        ]);
    }
}
