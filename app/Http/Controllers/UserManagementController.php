<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
}