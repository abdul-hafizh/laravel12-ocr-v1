<?php

namespace App\Http\Controllers;

use App\Models\MasterSkpd;
use App\Models\MasterKendaraan;
use App\Models\User;
use App\Services\ReminderNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterSkpdController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterSkpd::with('kendaraan');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('nomor_skpd', 'like', "%{$search}%")
                    ->orWhere('nama_pemilik', 'like', "%{$search}%")
                    ->orWhere('nomor_polisi', 'like', "%{$search}%");
            });
        }

        return Inertia::render('MasterSkpd/Index', [
            'skpds' => $query->orderBy('tanggal_jatuh_tempo')->paginate(10)->withQueryString(),
            'kendaraans' => MasterKendaraan::where('is_active', true)->orderBy('nomor_polisi')->get(),
            'financeUsers' => User::whereHas('role', function ($q) {
                $q->where('slug', 'finance');
            })
            ->where('is_active', true)
            ->where('is_delete', false)
            ->orderBy('name')
            ->get(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_kendaraan_id' => ['nullable', 'exists:master_kendaraans,id'],
            'nomor_skpd' => ['required', 'string', 'max:100', 'unique:master_skpds,nomor_skpd'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'nomor_polisi' => ['nullable', 'string', 'max:50'],
            'nominal_pajak' => ['required', 'numeric', 'min:0'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $skpd = MasterSkpd::create($validated);

        $this->syncReminderSkpd($skpd);

        return back()->with('success', 'Master SKPD berhasil ditambahkan.');
    }

    public function update(Request $request, MasterSkpd $masterSkpd)
    {
        $validated = $request->validate([
            'master_kendaraan_id' => ['nullable', 'exists:master_kendaraans,id'],
            'nomor_skpd' => ['required', 'string', 'max:100', 'unique:master_skpds,nomor_skpd,' . $masterSkpd->id],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'nomor_polisi' => ['nullable', 'string', 'max:50'],
            'nominal_pajak' => ['required', 'numeric', 'min:0'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $masterSkpd->update($validated);

        $this->syncReminderSkpd($masterSkpd->fresh());

        return back()->with('success', 'Master SKPD berhasil diperbarui.');
    }

    public function destroy(MasterSkpd $masterSkpd)
    {
        $masterSkpd->delete();

        return back()->with('success', 'Master SKPD berhasil dihapus.');
    }

    private function syncReminderSkpd(MasterSkpd $skpd): void
    {
        if (
            !$skpd->is_active ||
            !$skpd->tanggal_jatuh_tempo ||
            !$skpd->user_id
        ) {
            return;
        }

        ReminderNotificationService::createOrUpdate([
            'module' => 'skpd',
            'reference_id' => $skpd->id,
            'user_id' => $skpd->user_id,
            'due_date' => $skpd->tanggal_jatuh_tempo,
            'reminder_days' => $skpd->reminder_hari,
            'title' => 'Reminder Jatuh Tempo SKPD',
            'message' => $this->buildSkpdReminderMessage($skpd),
        ]);
    }

    private function buildSkpdReminderMessage(MasterSkpd $skpd): string
    {
        $nominal = number_format((float) $skpd->nominal_pajak, 0, ',', '.');

        return "Reminder Jatuh Tempo SKPD\n\n"
            . "Nomor SKPD: {$skpd->nomor_skpd}\n"
            . "Nomor Polisi: " . ($skpd->nomor_polisi ?: '-') . "\n"
            . "Nama Pemilik: " . ($skpd->nama_pemilik ?: '-') . "\n"
            . "Nominal Pajak: Rp {$nominal}\n"
            . "Jatuh Tempo: {$skpd->tanggal_jatuh_tempo->format('d-m-Y')}\n"
            . "Reminder: H-{$skpd->reminder_hari}\n\n"
            . "Mohon segera dilakukan pengecekan.";
    }
}