<?php

namespace App\Http\Controllers;

use App\Helpers\UserAccessHelper;
use App\Models\MasterCabang;
use App\Models\MasterSkpd;
use App\Models\User;
use App\Services\ReminderNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MasterSkpdController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = MasterSkpd::query()
            ->select('master_skpds.*')
            ->with('cabang')
            ->leftJoin(
                'master_cabangs',
                'master_skpds.master_cabang_id',
                '=',
                'master_cabangs.id'
            );

        UserAccessHelper::applyCabangFilter($query, $user, 'master_skpds.master_cabang_id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('master_skpds.jenis', 'like', "%{$search}%")
                    ->orWhere('master_skpds.keterangan', 'like', "%{$search}%")
                    ->orWhere('master_skpds.nomor_skpd', 'like', "%{$search}%")
                    ->orWhere('master_cabangs.nama_cabang', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('master_cabang_id')
            && UserAccessHelper::cabangIds($user)->contains((int) $request->master_cabang_id)
        ) {
            $query->where('master_skpds.master_cabang_id', $request->master_cabang_id);
        }

        return Inertia::render('MasterSkpd/Index', [
            'skpds' => $query
                ->orderBy('master_cabangs.nama_cabang')
                ->orderBy('master_skpds.tanggal_jatuh_tempo')
                ->paginate(10)
                ->withQueryString()
                ->through(function ($item) {
                    $fotos = is_array($item->foto) ? $item->foto : [];

                    $item->foto_urls = collect($fotos)
                        ->map(fn ($foto) => asset('storage/' . $foto))
                        ->values();

                    return $item;
                }),

            'cabangs' => MasterCabang::with(['users:id,name,phone'])
                ->whereIn('id', UserAccessHelper::cabangIds($user))
                ->orderBy('nama_cabang')
                ->get(['id', 'kode_cabang', 'nama_cabang']),

            'financeUsers' => User::where('is_active', true)
                ->where('is_delete', false)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),

            'filters' => $request->only(['search', 'master_cabang_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'jenis' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'tanggal_jatuh_tempo' => ['required', 'date'],

            'reminder_hari' => ['required', 'array', 'min:1'],
            'reminder_hari.*' => ['required', 'integer', 'in:7,14,30'],

            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'exists:users,id'],
            'nomor_skpd' => [
                'required',
                'string',
                'max:100',
                'unique:master_skpds,nomor_skpd'
            ],

            'nominal_pajak' => [
                'required',
                'numeric',
                'min:0'
            ],

            'foto' => ['nullable', 'array'],
            'foto.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = collect($request->file('foto'))
                ->map(fn ($file) => $file->store('skpd', 'public'))
                ->values()
                ->toArray();
        }

        $skpd = MasterSkpd::create($validated);
        $skpd->load('cabang');

        $this->syncReminderSkpd($skpd);

        return back()->with('message', ['text' => 'Master SKPD berhasil Ditambahkan!', 'type' => 'success']);
    }

    public function update(Request $request, MasterSkpd $masterSkpd)
    {
        $validated = $request->validate([
            'master_cabang_id' => ['required', 'exists:master_cabangs,id'],
            'jenis' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'tanggal_jatuh_tempo' => ['required', 'date'],

            'reminder_hari' => ['required', 'array', 'min:1'],
            'reminder_hari.*' => ['required', 'integer', 'in:7,14,30'],

            'deleted_fotos' => ['nullable', 'array'],
            'deleted_fotos.*' => ['string'],

            'nomor_skpd' => [
                'required',
                'string',
                'max:100',
                'unique:master_skpds,nomor_skpd,' . $masterSkpd->id
            ],

            'nominal_pajak' => [
                'required',
                'numeric',
                'min:0'
            ],

            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'exists:users,id'],

            'foto' => ['nullable', 'array'],
            'foto.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['boolean'],
        ]);

        $existingFotos = is_array($masterSkpd->foto) ? $masterSkpd->foto : [];

        $deletedFotos = $validated['deleted_fotos'] ?? [];

        if (!empty($deletedFotos)) {
            Storage::disk('public')->delete($deletedFotos);

            $existingFotos = array_values(array_filter(
                $existingFotos,
                fn ($foto) => !in_array($foto, $deletedFotos)
            ));
        }

        if ($request->hasFile('foto')) {
            $newFotos = collect($request->file('foto'))
                ->map(fn ($file) => $file->store('skpd', 'public'))
                ->values()
                ->toArray();

            $existingFotos = array_merge($existingFotos, $newFotos);
        }

        $validated['foto'] = $existingFotos;

        unset($validated['deleted_fotos']);

        $masterSkpd->update($validated);
        $masterSkpd->refresh()->load('cabang');

        $this->syncReminderSkpd($masterSkpd);

        return back()->with('message', ['text' => 'Master SKPD berhasil diperbarui!', 'type' => 'success']);
    }

    public function destroy(MasterSkpd $masterSkpd)
    {
        if (is_array($masterSkpd->foto)) {
            Storage::disk('public')->delete($masterSkpd->foto);
        }

        $masterSkpd->delete();

        return back()->with('message', ['text' => 'Master SKPD berhasil dihapus!', 'type' => 'success']);
    }

    private function syncReminderSkpd(MasterSkpd $skpd): void
    {
        if (
            !$skpd->is_active ||
            !$skpd->tanggal_jatuh_tempo ||
            empty($skpd->user_ids) ||
            empty($skpd->reminder_hari)
        ) {
            return;
        }

        foreach ($skpd->user_ids as $userId) {
            foreach ($skpd->reminder_hari as $reminderDay) {
                ReminderNotificationService::createOrUpdate([
                    'module' => 'skpd',
                    'reference_id' => $skpd->id,
                    'user_id' => $userId,
                    'due_date' => $skpd->tanggal_jatuh_tempo,
                    'reminder_days' => $reminderDay,
                    'title' => 'Reminder Jatuh Tempo SKPD',
                    'message' => $this->buildSkpdReminderMessage($skpd, $reminderDay),
                ]);
            }
        }
    }

    private function buildSkpdReminderMessage(MasterSkpd $skpd, int $reminderDay): string
    {
        $nominal = number_format(
            (float) $skpd->nominal_pajak,
            0,
            ',',
            '.'
        );

        return "Reminder Jatuh Tempo SKPD\n\n"
            . "Cabang: " . ($skpd->cabang?->nama_cabang ?? '-') . "\n"
            . "Nomor SKPD: " . ($skpd->nomor_skpd ?: '-') . "\n"
            . "Jenis: " . ($skpd->jenis ?: '-') . "\n"
            . "Nominal Pajak: Rp {$nominal}\n"
            . "Keterangan: " . ($skpd->keterangan ?: '-') . "\n"
            . "Jatuh Tempo: "
            . optional($skpd->tanggal_jatuh_tempo)->format('d-m-Y')
            . "\n"
            . "Reminder: H-{$reminderDay}\n\n"
            . "Mohon segera dilakukan pengecekan.";
    }
}