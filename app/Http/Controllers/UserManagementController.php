<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role')
            ->where('is_delete', false);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'employee_id' => ['nullable', 'string', 'max:100', 'unique:users,employee_id'],
            'phone' => [
                'nullable',
                'regex:/^628[0-9]{8,15}$/'
            ],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'role_id' => ['nullable', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'employee_id' => $validated['employee_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
            'is_active' => $validated['is_active'],
            'is_delete' => false,
        ]);

        return back()->with('message', ['text' => 'Master User berhasil ditambahkan!', 'type' => 'success']);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'employee_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('users', 'employee_id')->ignore($user->id),
            ],
            'phone' => [
                'nullable',
                'regex:/^628[0-9]{8,15}$/'
            ],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'role_id' => ['nullable', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'employee_id' => $validated['employee_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
            'is_active' => $validated['is_active'],
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return back()->with('message', ['text' => 'Master User berhasil diperbarui!', 'type' => 'success']);
    }

    public function destroy(User $user)
    {
        $user->update([
            'is_delete' => true,
            'is_active' => false,
        ]);

        return back()->with('message', ['text' => 'Master User berhasil dihapus!', 'type' => 'success']);
    }

    public function sync()
    {
        $defaultRoleId = 1; // STAFF

        $roleMap = DB::table('roles')
            ->where('is_active', 1)
            ->pluck('id', 'slug')
            ->mapWithKeys(function ($id, $slug) {
                return [strtoupper(trim($slug)) => $id];
            })
            ->toArray();

        $cabangMap = DB::table('master_cabangs')
            ->where('is_active', 1)
            ->pluck('id', 'kode_cabang')
            ->mapWithKeys(function ($id, $kodeCabang) {
                return [strtoupper(trim($kodeCabang)) => $id];
            })
            ->toArray();

        $rows = DB::connection('sqlsrv_external')
            ->table('db_laporan.dbo.w_user')
            ->select([
                'id',
                'name',
                'employeeid',
                'password',
                'phone',
                'email',
                'gender',
                'kodeJabatan',
                'kodeCabang',
                'isActive',
                'isDelete',
            ])
            ->whereNotNull('employeeid')
            ->orderBy('id')
            ->get();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $cabangSynced = 0;
        $cabangNotFound = 0;

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $employeeId = trim((string) $row->employeeid);

                if ($employeeId === '') {
                    $skipped++;
                    continue;
                }

                $kodeJabatan = strtoupper(trim((string) ($row->kodeJabatan ?? '')));
                $roleId = $roleMap[$kodeJabatan] ?? $defaultRoleId;

                $kodeCabang = strtoupper(trim((string) ($row->kodeCabang ?? '')));
                $masterCabangId = $kodeCabang !== ''
                    ? ($cabangMap[$kodeCabang] ?? null)
                    : null;

                $email = trim((string) ($row->email ?? ''));

                if ($email === '') {
                    $email = strtolower($employeeId) . '@noemail.local';
                }

                $existing = DB::table('users')
                    ->where('employee_id', $employeeId)
                    ->orWhere('email', $email)
                    ->first();

                $data = [
                    'name' => $row->name ?: $employeeId,
                    'email' => $email,
                    'employee_id' => $employeeId,
                    'password' => $row->password,
                    'phone' => $this->normalizePhone($row->phone),
                    'gender' => $row->gender,
                    'role_id' => $roleId,
                    'is_active' => strtoupper(trim((string) ($row->isActive ?? 'Y'))) === 'Y' ? 1 : 0,
                    'is_delete' => 0,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('users')
                        ->where('id', $existing->id)
                        ->update($data);

                    $userId = $existing->id;
                    $updated++;
                } else {
                    $data['created_at'] = now();

                    $userId = DB::table('users')->insertGetId($data);
                    $inserted++;
                }

                DB::table('master_cabang_user')
                    ->where('user_id', $userId)
                    ->delete();

                if ($masterCabangId) {
                    DB::table('master_cabang_user')->insert([
                        'master_cabang_id' => $masterCabangId,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $cabangSynced++;
                } elseif ($kodeCabang !== '') {
                    $cabangNotFound++;
                }
            }

            DB::commit();

            return back()->with('message', [
                'text' => "Sync user berhasil. Baru: {$inserted}, Update: {$updated}, Cabang: {$cabangSynced}, Cabang tidak ditemukan: {$cabangNotFound}, Skip: {$skipped}",
                'type' => 'success',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            report($e);

            return back()->with('message', [
                'text' => 'Sync user gagal: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '08')) {
            return '628' . substr($phone, 2);
        }

        if (str_starts_with($phone, '8')) {
            return '62' . $phone;
        }

        return $phone;
    }

}