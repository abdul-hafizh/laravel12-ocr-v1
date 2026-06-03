<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RoleWhatsappMenu;
use App\Models\RoleUrlPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::with([
            'whatsappMenus',
            'urlPermissions',
        ]);

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
            'urlMenus' => $this->urlMenus(),
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

    private function urlMenus(): array
    {
        return [
            ['url' => '/dashboard', 'label' => 'Dashboard'],

            ['url' => '/summary/electricity', 'label' => 'Summary - Token Listrik'],
            ['url' => '/summary/printer-billing', 'label' => 'Summary - Meter Mesin'],

            ['url' => '/hasil-upload/token-listrik', 'label' => 'Hasil Upload - Token Listrik'],
            ['url' => '/hasil-upload/mesin-cetak', 'label' => 'Hasil Upload - Meter Mesin'],
            ['url' => '/hasil-upload/struk-online', 'label' => 'Hasil Upload - Bukti Bayar'],

            ['url' => '/master-cabang', 'label' => 'Master Cabang'],
            ['url' => '/master-vendor', 'label' => 'Master Vendor'],
            ['url' => '/master-mesin', 'label' => 'Master Mesin'],
            ['url' => '/master-token-listrik', 'label' => 'Master Token Listrik'],
            ['url' => '/master-kendaraan', 'label' => 'Master Kendaraan'],
            ['url' => '/master-skpd', 'label' => 'Master SKPD'],
            ['url' => '/master-harga-biaya', 'label' => 'Master Biaya'],

            ['url' => '/employee-measurements', 'label' => 'BMI Karyawan'],

            ['url' => '/roles', 'label' => 'Master Role'],
            ['url' => '/users-management', 'label' => 'Manajemen User'],

            ['url' => '/profile', 'label' => 'Settings'],
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

            'url_permissions' => ['nullable', 'array'],
            'url_permissions.*' => ['string'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $this->syncWhatsappMenus($role, $validated['whatsapp_menu_keys'] ?? []);
        $this->syncUrlPermissions($role, $validated['url_permissions'] ?? []);

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

            'url_permissions' => ['nullable', 'array'],
            'url_permissions.*' => ['string'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $this->syncWhatsappMenus($role, $validated['whatsapp_menu_keys'] ?? []);
        $this->syncUrlPermissions($role, $validated['url_permissions'] ?? []);

        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' => 'Role tidak bisa dihapus karena masih digunakan user.',
            ]);
        }

        $role->whatsappMenus()->delete();
        $role->urlPermissions()->delete();

        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }

    private function syncWhatsappMenus(Role $role, array $menuKeys): void
    {
        $role->whatsappMenus()->delete();

        foreach ($menuKeys as $menuKey) {
            RoleWhatsappMenu::create([
                'role_id' => $role->id,
                'menu_key' => $menuKey,
                'is_active' => true,
            ]);
        }
    }

    private function syncUrlPermissions(Role $role, array $urls): void
    {
        $role->urlPermissions()->delete();

        foreach ($urls as $url) {
            RoleUrlPermission::create([
                'role_id' => $role->id,
                'url' => $url,
                'is_active' => true,
            ]);
        }
    }
}