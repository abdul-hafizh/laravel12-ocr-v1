<?php

namespace App\Http\Controllers;

use App\Models\MasterKendaraan;
use App\Models\MasterCabang;
use App\Models\User;
use App\Libraries\SendSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\ReminderNotificationService;

class MasterKendaraanController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterKendaraan::with(['cabang', 'financeUser']);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('nomor_polisi', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%")
                    ->orWhere('tipe', 'like', "%{$search}%")
                    ->orWhere('nama_pemilik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('master_cabang_id')) {
            $query->where('master_cabang_id', $request->master_cabang_id);
        }

        $financeUsers = User::with('role')
            ->where('is_active', true)
            ->where('is_delete', false)
            ->whereHas('role', function ($q) {
                $q->where('slug', 'finance')
                    ->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'role_id']);

        return Inertia::render('MasterKendaraan/Index', [
            'kendaraans' => $query->orderBy('nomor_polisi')->paginate(10)->withQueryString(),
            'cabangs' => MasterCabang::where('is_active', true)->orderBy('nama_cabang')->get(),
            'financeUsers' => $financeUsers,
            'filters' => $request->only(['search', 'master_cabang_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['nullable', 'exists:master_cabangs,id'],
            'finance_user_id' => ['required', 'exists:users,id'],
            'jenis_kendaraan' => ['required', 'string', 'max:100'],
            'nomor_polisi' => ['required', 'string', 'max:50', 'unique:master_kendaraans,nomor_polisi'],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $financeUser = User::where('id', $validated['finance_user_id'])
            ->whereHas('role', fn ($q) => $q->where('slug', 'finance'))
            ->firstOrFail();

        $kendaraan = MasterKendaraan::create($validated);
        $kendaraan->load('cabang');

        if (!empty($validated['tanggal_jatuh_tempo'])) {
            ReminderNotificationService::createOrUpdate([
                'module' => 'kendaraan',
                'reference_id' => $kendaraan->id,
                'user_id' => $validated['finance_user_id'],
                'due_date' => $validated['tanggal_jatuh_tempo'],
                'reminder_days' => $validated['reminder_hari'],
                'title' => 'Reminder Pajak Kendaraan',
                'message' => "Halo Finance,\n\n"
                    . "Reminder jatuh tempo pajak kendaraan.\n\n"
                    . "No. Polisi: {$kendaraan->nomor_polisi}\n"
                    . "Jenis: {$kendaraan->jenis_kendaraan}\n"
                    . "Merk/Tipe: {$kendaraan->merk} {$kendaraan->tipe}\n"
                    . "Cabang: " . ($kendaraan->cabang?->nama_cabang ?? '-') . "\n"
                    . "Jatuh Tempo: " . optional($kendaraan->tanggal_jatuh_tempo)->format('d-m-Y') . "\n"
                    . "Reminder: H-{$kendaraan->reminder_hari}\n\n"
                    . "Mohon segera dilakukan pengecekan dan tindak lanjut.\n\n"
                    . "Terima kasih.",
            ]);
        }

        return back()->with('success', 'Master kendaraan berhasil ditambahkan dan notifikasi WA berhasil diproses.');
    }

    public function update(Request $request, MasterKendaraan $masterKendaraan)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['nullable', 'exists:master_cabangs,id'],
            'finance_user_id' => ['required', 'exists:users,id'],
            'jenis_kendaraan' => ['required', 'string', 'max:100'],
            'nomor_polisi' => ['required', 'string', 'max:50', 'unique:master_kendaraans,nomor_polisi,' . $masterKendaraan->id],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'reminder_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        User::where('id', $validated['finance_user_id'])
            ->whereHas('role', fn ($q) => $q->where('slug', 'finance'))
            ->firstOrFail();

        $masterKendaraan->update($validated);
        $masterKendaraan->refresh();
        $masterKendaraan->load('cabang');

        if (!empty($validated['tanggal_jatuh_tempo'])) {
            ReminderNotificationService::createOrUpdate([
                'module' => 'kendaraan',
                'reference_id' => $masterKendaraan->id,
                'user_id' => $validated['finance_user_id'],
                'due_date' => $validated['tanggal_jatuh_tempo'],
                'reminder_days' => $validated['reminder_hari'],
                'title' => 'Reminder Pajak Kendaraan',
                'message' => "Halo Finance,\n\n"
                    . "Reminder jatuh tempo pajak kendaraan.\n\n"
                    . "No. Polisi: {$masterKendaraan->nomor_polisi}\n"
                    . "Jenis: {$masterKendaraan->jenis_kendaraan}\n"
                    . "Merk/Tipe: {$masterKendaraan->merk} {$masterKendaraan->tipe}\n"
                    . "Cabang: " . ($masterKendaraan->cabang?->nama_cabang ?? '-') . "\n"
                    . "Jatuh Tempo: " . optional($masterKendaraan->tanggal_jatuh_tempo)->format('d-m-Y') . "\n"
                    . "Reminder: H-{$masterKendaraan->reminder_hari}\n\n"
                    . "Mohon segera dilakukan pengecekan dan tindak lanjut.\n\n"
                    . "Terima kasih.",
            ]);
        }

        return back()->with('success', 'Master kendaraan berhasil diperbarui.');
    }

    public function destroy(MasterKendaraan $masterKendaraan)
    {
        $masterKendaraan->delete();

        return back()->with('success', 'Master kendaraan berhasil dihapus.');
    }

    private function buildVehicleReminderMessage(User $user, MasterKendaraan $kendaraan): string
    {
        $jatuhTempo = $kendaraan->tanggal_jatuh_tempo
            ? $kendaraan->tanggal_jatuh_tempo->format('d-m-Y')
            : '-';

        $cabang = $kendaraan->cabang?->nama_cabang ?? '-';

        return "Halo {$user->name},\n\n"
            . "Ada data kendaraan baru yang perlu dipantau oleh Finance.\n\n"
            . "Detail Kendaraan:\n"
            . "No. Polisi: {$kendaraan->nomor_polisi}\n"
            . "Jenis: {$kendaraan->jenis_kendaraan}\n"
            . "Merk/Tipe: {$kendaraan->merk} {$kendaraan->tipe}\n"
            . "Cabang: {$cabang}\n"
            . "Pemilik STNK: " . ($kendaraan->nama_pemilik ?: '-') . "\n"
            . "Jatuh Tempo Pajak: {$jatuhTempo}\n"
            . "Reminder: H-{$kendaraan->reminder_hari}\n\n"
            . "Mohon dilakukan pengecekan dan tindak lanjut sesuai jadwal.\n\n"
            . "Terima kasih.";
    }
}