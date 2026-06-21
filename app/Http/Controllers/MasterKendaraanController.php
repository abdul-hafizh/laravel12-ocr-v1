<?php

namespace App\Http\Controllers;

use App\Helpers\UserAccessHelper;
use App\Models\MasterKendaraan;
use App\Models\MasterCabang;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\ReminderNotificationService;

class MasterKendaraanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = MasterKendaraan::with(['cabang', 'financeUser']);

        UserAccessHelper::applyCabangFilter($query, $user);

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

        if (
            $request->filled('master_cabang_id')
            && UserAccessHelper::cabangIds($user)->contains((int) $request->master_cabang_id)
        ) {
            $query->where('master_cabang_id', $request->master_cabang_id);
        }

        $financeUsers = User::with('role')
            ->where('is_active', true)
            ->where('is_delete', false)
            ->whereHas('role', function ($q) {
                $q->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'role_id']);

        return Inertia::render('MasterKendaraan/Index', [
            'kendaraans' => $query->orderBy('nomor_polisi')->paginate(10)->withQueryString(),
            'cabangs' => MasterCabang::whereIn('id', UserAccessHelper::cabangIds($user))->orderBy('nama_cabang')->get(),
            'financeUsers' => $financeUsers,
            'filters' => $request->only(['search', 'master_cabang_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $validated['finance_user_id'] = $validated['finance_user_ids'][0] ?? null;

        $this->validateFinanceUsers($validated['finance_user_ids']);

        if (empty($validated['tanggal_ganti_kaleng']) && !empty($validated['tanggal_jatuh_tempo'])) {
            $validated['tanggal_ganti_kaleng'] = Carbon::parse($validated['tanggal_jatuh_tempo'])->addYears(5)->toDateString();
        }

        $kendaraan = MasterKendaraan::create($validated);
        $kendaraan->load('cabang');

        $this->syncReminderPajak($kendaraan);
        $this->syncReminderGantiKaleng($kendaraan);

        return back()->with('message', ['text' => 'Master Kendaraan berhasil ditambahkan dan reminder berhasil diprosess!', 'type' => 'success']);
    }

    public function update(Request $request, MasterKendaraan $masterKendaraan)
    {
        $validated = $this->validateRequest($request, $masterKendaraan->id);

        $validated['finance_user_id'] = $validated['finance_user_ids'][0] ?? null;

        $this->validateFinanceUsers($validated['finance_user_ids']);

        if (empty($validated['tanggal_ganti_kaleng']) && !empty($validated['tanggal_jatuh_tempo'])) {
            $validated['tanggal_ganti_kaleng'] = Carbon::parse($validated['tanggal_jatuh_tempo'])->addYears(5)->toDateString();
        }

        $masterKendaraan->update($validated);
        $masterKendaraan->refresh();
        $masterKendaraan->load('cabang');

        $this->syncReminderPajak($masterKendaraan);
        $this->syncReminderGantiKaleng($masterKendaraan);

        return back()->with('message', ['text' => 'Master Kendaraan berhasil diperbarui!', 'type' => 'success']);
    }

    public function destroy(MasterKendaraan $masterKendaraan)
    {
        $masterKendaraan->delete();

        return back()->with('message', ['text' => 'Master Kendaraan berhasil dihapus!', 'type' => 'success']);
    }

    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'master_cabang_id' => ['nullable', 'exists:master_cabangs,id'],

            'finance_user_ids' => ['required', 'array', 'min:1'],
            'finance_user_ids.*' => ['required', 'exists:users,id'],

            'reminder_hari' => ['required', 'array', 'min:1'],
            'reminder_hari.*' => ['required', 'integer', 'in:7,14,30'],

            'reminder_ganti_kaleng_hari' => ['nullable', 'array'],
            'reminder_ganti_kaleng_hari.*' => ['required', 'integer', 'in:7,14,30,60,90'],

            'jenis_kendaraan' => ['required', 'string', 'max:100'],
            'nomor_polisi' => [
                'required',
                'string',
                'max:50',
                'unique:master_kendaraans,nomor_polisi' . ($id ? ',' . $id : ''),
            ],
            'merk' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'nama_pemilik' => ['nullable', 'string', 'max:255'],

            'tanggal_jatuh_tempo' => ['required', 'date'],
            'tanggal_ganti_kaleng' => ['nullable', 'date'],

            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateFinanceUsers(array $financeUserIds): void
    {
        $financeUsers = User::whereIn('id', $financeUserIds)
            ->whereHas('role', fn ($q) => $q->where('slug', 'finance'))
            ->get();

        if ($financeUsers->count() !== count($financeUserIds)) {
            abort(422, 'User finance tidak valid.');
        }
    }

    private function syncReminderPajak(MasterKendaraan $kendaraan): void
    {
        if (empty($kendaraan->tanggal_jatuh_tempo)) {
            return;
        }

        foreach ($kendaraan->finance_user_ids ?? [] as $userId) {
            foreach ($kendaraan->reminder_hari ?? [] as $reminderDay) {
                ReminderNotificationService::createOrUpdate([
                    'module' => 'kendaraan_pajak',
                    'reference_id' => $kendaraan->id,
                    'user_id' => $userId,
                    'due_date' => $kendaraan->tanggal_jatuh_tempo,
                    'reminder_days' => $reminderDay,
                    'title' => 'Reminder Pajak Kendaraan',
                    'message' => $this->buildPajakMessage($kendaraan, $reminderDay),
                ]);
            }
        }
    }

    private function syncReminderGantiKaleng(MasterKendaraan $kendaraan): void
    {
        if (empty($kendaraan->tanggal_ganti_kaleng)) {
            return;
        }

        $reminderDays = $kendaraan->reminder_ganti_kaleng_hari ?: [90, 60, 30];

        foreach ($kendaraan->finance_user_ids ?? [] as $userId) {
            foreach ($reminderDays as $reminderDay) {
                ReminderNotificationService::createOrUpdate([
                    'module' => 'kendaraan_ganti_kaleng',
                    'reference_id' => $kendaraan->id,
                    'user_id' => $userId,
                    'due_date' => $kendaraan->tanggal_ganti_kaleng,
                    'reminder_days' => $reminderDay,
                    'title' => 'Reminder Ganti Kaleng Kendaraan',
                    'message' => $this->buildGantiKalengMessage($kendaraan, $reminderDay),
                ]);
            }
        }
    }

    private function buildPajakMessage(MasterKendaraan $kendaraan, int $reminderDay): string
    {
        return "Halo Finance,\n\n"
            . "Reminder jatuh tempo pajak kendaraan.\n\n"
            . "No. Polisi: {$kendaraan->nomor_polisi}\n"
            . "Jenis: {$kendaraan->jenis_kendaraan}\n"
            . "Merk/Tipe: {$kendaraan->merk} {$kendaraan->tipe}\n"
            . "Cabang: " . ($kendaraan->cabang?->nama_cabang ?? '-') . "\n"
            . "Jatuh Tempo Pajak: " . optional($kendaraan->tanggal_jatuh_tempo)->format('d-m-Y') . "\n"
            . "Reminder: H-{$reminderDay}\n"
            . "Keterangan: " . ($kendaraan->keterangan ?: '-') . "\n\n"
            . "Mohon segera dilakukan pengecekan dan tindak lanjut.\n\n"
            . "Terima kasih.";
    }

    private function buildGantiKalengMessage(MasterKendaraan $kendaraan, int $reminderDay): string
    {
        return "Halo Finance,\n\n"
            . "Reminder ganti kaleng / perpanjangan STNK 5 tahunan kendaraan.\n\n"
            . "No. Polisi: {$kendaraan->nomor_polisi}\n"
            . "Jenis: {$kendaraan->jenis_kendaraan}\n"
            . "Merk/Tipe: {$kendaraan->merk} {$kendaraan->tipe}\n"
            . "Cabang: " . ($kendaraan->cabang?->nama_cabang ?? '-') . "\n"
            . "Pemilik STNK: " . ($kendaraan->nama_pemilik ?: '-') . "\n"
            . "Tanggal Ganti Kaleng: " . optional($kendaraan->tanggal_ganti_kaleng)->format('d-m-Y') . "\n"
            . "Reminder: H-{$reminderDay}\n"
            . "Keterangan: " . ($kendaraan->keterangan ?: '-') . "\n\n"
            . "Mohon segera dilakukan pengecekan dan tindak lanjut untuk proses ganti kaleng kendaraan.\n\n"
            . "Terima kasih.";
    }
}