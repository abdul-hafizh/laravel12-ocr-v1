<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RoleWhatsappMenu;
use App\Models\RoleUrlPermission;
use App\Models\RoleActionPermission;
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
            'actionPermissions',
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
            'actionPermissions' => $this->actionPermissions(),
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    private function actionPermissions(): array
    {
        return [
            [
                'key' => 'DELETE_IMAGE_SCAN',
                'label' => 'Hapus Data Hasil Scan',
            ],
        ];
    }

    private function whatsappMenus(): array
    {
        return [
            ['key' => 'BMI', 'label' => '1 - BMI'],
            ['key' => 'BIAYA_UMUM', 'label' => '2 - Biaya Umum'],
            ['key' => 'BIAYA_TOKEN_LISTRIK', 'label' => '3 - Biaya Token Listrik'],
            ['key' => 'BIAYA_KLIK_METER', 'label' => '4 - Biaya Klik Meter'],
            ['key' => 'MESIN_CEA', 'label' => '5 - Mesin CEA'],
            ['key' => 'ASABA', 'label' => '6 - Asaba'],
            ['key' => 'BIAYA_PART', 'label' => '7 - Biaya Part'],
            ['key' => 'MAINTENANCE_MESIN', 'label' => '8 - Maintenance Mesin'],
        ];
    }

    private function urlMenus(): array
    {
        return [
            ['url' => '/dashboard', 'label' => 'Dashboard'],

            ['url' => '/summary/electricity', 'label' => 'Report - Token Listrik'],
            ['url' => '/summary/printer-billing', 'label' => 'Report - Meter Mesin'],
            ['url' => '/summary/asaba', 'label' => 'Report - Asaba'],
            ['url' => '/summary/cea', 'label' => 'Report - CEA'],

            ['url' => '/hasil-upload/token-listrik', 'label' => 'Hasil Upload - Token Listrik'],
            ['url' => '/hasil-upload/mesin-cetak', 'label' => 'Hasil Upload - Meter Mesin'],
            ['url' => '/hasil-upload/struk-online', 'label' => 'Hasil Upload - Bukti Bayar'],
            ['url' => '/hasil-upload/part-maintenance', 'label' => 'Hasil Upload - Part & Maintenance'],
            ['url' => '/hasil-upload/cea', 'label' => 'Hasil Upload - Counter CEA'],
            ['url' => '/hasil-upload/asaba', 'label' => 'Hasil Upload - Counter Asaba'],

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
            
            'action_permissions' => ['nullable', 'array'],
            'action_permissions.*' => ['string'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $this->syncWhatsappMenus($role, $validated['whatsapp_menu_keys'] ?? []);
        $this->syncUrlPermissions($role, $validated['url_permissions'] ?? []);
        $this->syncActionPermissions($role, $validated['action_permissions'] ?? []);

        return back()->with('message', ['text' => 'Role berhasil ditambahkan!', 'type' => 'success']);
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

            'action_permissions' => ['nullable', 'array'],
            'action_permissions.*' => ['string'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $this->syncWhatsappMenus($role, $validated['whatsapp_menu_keys'] ?? []);
        $this->syncUrlPermissions($role, $validated['url_permissions'] ?? []);
        $this->syncActionPermissions($role, $validated['action_permissions'] ?? []);

        return back()->with('message', ['text' => 'Role berhasil diperbarui!', 'type' => 'success']);
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

        return back()->with('message', ['text' => 'Role berhasil dihapus!', 'type' => 'success']);
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

    private function syncActionPermissions(Role $role, array $actions): void
    {
        $role->actionPermissions()->delete();

        foreach ($actions as $action) {
            RoleActionPermission::create([
                'role_id' => $role->id,
                'action_key' => $action,
                'is_active' => true,
            ]);
        }
    }
}