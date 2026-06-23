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
        $defaultRoleId = 1; // default role id

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
            ])
            ->whereNotNull('email')
            ->orderBy('id')
            ->get();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $email = trim((string) $row->email);

                if ($email === '') {
                    $skipped++;
                    continue;
                }

                $existing = DB::table('users')
                    ->where('email', $email)
                    ->first();

                $data = [
                    'name' => $row->name ?: $email,
                    'email' => $email,
                    'employee_id' => $row->employeeid,
                    'password' => $row->password,
                    'phone' => $this->normalizePhone($row->phone),
                    'gender' => $row->gender,
                    'role_id' => 1, // default role id
                    'is_active' => true,
                    'is_delete' => false,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('users')
                        ->where('id', $existing->id)
                        ->update($data);

                    $updated++;
                } else {
                    $data['created_at'] = now();

                    DB::table('users')->insert($data);

                    $inserted++;
                }
            }

            DB::commit();

            return back()->with('message', [
                'text' => "Sync user berhasil. Baru: {$inserted}, Update: {$updated}, Skip: {$skipped}",
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