<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RoleWhatsappMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::with('whatsappMenus');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $roles = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'whatsappMenus' => $this->whatsappMenus(),
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    private function whatsappMenus(): array
    {
        return [
            ['key' => 'BMI', 'label' => '1 - BMI'],
            ['key' => 'BIAYA_UMUM', 'label' => '2 - Biaya Umum'],
            ['key' => 'BIAYA_TOKEN_LISTRIK', 'label' => '3 - Biaya Token Listrik'],
            ['key' => 'BIAYA_KLIK_METER', 'label' => '4 - Biaya Klik Meter'],
            ['key' => 'BIAYA_PART', 'label' => '5 - Biaya Part'],
            ['key' => 'MAINTENANCE_MESIN', 'label' => '6 - Maintenance Mesin'],
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'whatsapp_menu_keys' => ['nullable', 'array'],
            'whatsapp_menu_keys.*' => ['string'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        foreach ($validated['whatsapp_menu_keys'] ?? [] as $menuKey) {
            RoleWhatsappMenu::create([
                'role_id' => $role->id,
                'menu_key' => $menuKey,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Role berhasil ditambahkan.');
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'whatsapp_menu_keys' => ['nullable', 'array'],
            'whatsapp_menu_keys.*' => ['string'],
        ]);

        $role->whatsappMenus()->delete();

        foreach ($validated['whatsapp_menu_keys'] ?? [] as $menuKey) {
            RoleWhatsappMenu::create([
                'role_id' => $role->id,
                'menu_key' => $menuKey,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' => 'Role tidak bisa dihapus karena masih digunakan user.',
            ]);
        }

        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }
}