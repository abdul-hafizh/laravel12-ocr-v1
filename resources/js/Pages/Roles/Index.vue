<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    roles: Object,
    filters: Object,
    whatsappMenus: {
        type: Array,
        default: () => [],
    },
    urlMenus: {
        type: Array,
        default: () => [],
    },
    actionPermissions: {
        type: Array,
        default: () => [],
    },
});

const defaultUrlMenus = [
    { url: '/dashboard', label: 'Dashboard' },

    { url: '/summary/electricity', label: 'Summary - Token Listrik' },
    { url: '/summary/printer-billing', label: 'Summary - Meter Mesin' },

    { url: '/hasil-upload/token-listrik', label: 'Hasil Upload - Token Listrik' },
    { url: '/hasil-upload/mesin-cetak', label: 'Hasil Upload - Meter Mesin' },
    { url: '/hasil-upload/struk-online', label: 'Hasil Upload - Bukti Bayar' },

    { url: '/master-cabang', label: 'Master Cabang' },
    { url: '/master-vendor', label: 'Master Vendor' },
    { url: '/master-mesin', label: 'Master Mesin' },
    { url: '/master-token-listrik', label: 'Master Token Listrik' },
    { url: '/master-kendaraan', label: 'Master Kendaraan' },
    { url: '/master-skpd', label: 'Master SKPD' },
    { url: '/master-harga-biaya', label: 'Master Biaya' },

    { url: '/employee-measurements', label: 'BMI Karyawan' },
    { url: '/roles', label: 'Master Role' },
    { url: '/users-management', label: 'Manajemen User' },
    { url: '/profile', label: 'Settings' },
];

const urlOptions = computed(() => {
    return props.urlMenus?.length ? props.urlMenus : defaultUrlMenus;
});

const defaultWhatsappMenus = [
    { key: 'BMI', label: '1 - BMI' },
    { key: 'BIAYA_UMUM', label: '2 - Biaya Umum' },
    { key: 'BIAYA_TOKEN_LISTRIK', label: '3 - Biaya Token Listrik' },
    { key: 'BIAYA_KLIK_METER', label: '4 - Biaya Klik Meter' },
    { key: 'MESIN_CEA', label: '5 - Mesin CEA' },
    { key: 'ASABA', label: '6 - Asaba' },
    { key: 'BIAYA_PART', label: '7 - Biaya Part' },
    { key: 'MAINTENANCE_MESIN', label: '8 - Maintenance Mesin' },
];

const menuOptions = computed(() => {
    return props.whatsappMenus?.length ? props.whatsappMenus : defaultWhatsappMenus;
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    name: '',
    description: '',
    is_active: true,
    whatsapp_menu_keys: [],
    url_permissions: [],
    action_permissions: [],
});

const getRoleUrlPermissions = (item) => {
    if (!Array.isArray(item.url_permissions)) {
        return [];
    }

    return item.url_permissions
        .filter((permission) => permission.is_active)
        .map((permission) => permission.url);
};

const getUrlLabel = (url) => {
    return urlOptions.value.find((item) => item.url === url)?.label || url;
};

watch(search, (value) => {
    router.get(
        route('roles.index'),
        { search: value, page: 1 },
        { preserveState: true, replace: true }
    );
});

const getRoleMenuKeys = (item) => {
    if (!Array.isArray(item.whatsapp_menus)) {
        return [];
    }

    return item.whatsapp_menus
        .filter((menu) => menu.is_active)
        .map((menu) => menu.menu_key);
};

const getMenuLabel = (key) => {
    return menuOptions.value.find((menu) => menu.key === key)?.label || key;
};

const getRoleActionPermissions = (item) => {
    if (!Array.isArray(item.action_permissions)) {
        return [];
    }

    return item.action_permissions
        .filter((permission) => permission.is_active)
        .map((permission) => permission.action_key);
};

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    form.whatsapp_menu_keys = [];
    form.url_permissions = [];
    form.action_permissions = [];
    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;
    form.clearErrors();

    form.name = item.name;
    form.description = item.description || '';
    form.is_active = Boolean(item.is_active);
    form.whatsapp_menu_keys = getRoleMenuKeys(item);
    form.url_permissions = getRoleUrlPermissions(item);
    form.action_permissions = getRoleActionPermissions(item);

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('roles.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('roles.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const showDeleteModal = ref(false);
const idToDelete = ref(null);
const isDeleting = ref(false);

const confirmDelete = (id) => {
    idToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    isDeleting.value = true;

    router.delete(route('roles.destroy', idToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            idToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Master Role" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Master <span class="text-[#2DD4BF]">Role</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="relative flex-1 max-w-sm">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari role..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all placeholder:text-slate-400"
                    />
                </div>

                <button
                    @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all flex items-center shadow-lg shadow-black/5"
                >
                    <span class="mr-2 text-sm">+</span> Tambah Role
                </button>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Nama Role
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Slug
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Deskripsi
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Menu WA
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Akses URL
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Status
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="roles.data.length > 0">
                                <tr
                                    v-for="item in roles.data"
                                    :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors"
                                >
                                    <td class="px-6 py-4 text-sm font-bold text-[#1E293B] uppercase">
                                        {{ item.name }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="text-[11px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2.5 py-1 rounded-lg">
                                            {{ item.slug }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-bold text-slate-500">
                                        {{ item.description || '-' }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <div
                                            v-if="getRoleMenuKeys(item).length"
                                            class="flex flex-wrap gap-1.5 max-w-md"
                                        >
                                            <span
                                                v-for="key in getRoleMenuKeys(item)"
                                                :key="key"
                                                class="px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-100 text-[9px] font-black text-slate-500 uppercase tracking-wider"
                                            >
                                                {{ getMenuLabel(key) }}
                                            </span>
                                        </div>

                                        <span
                                            v-else
                                            class="text-[10px] font-black text-rose-400 uppercase tracking-widest"
                                        >
                                            Tidak ada akses
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div
                                            v-if="getRoleUrlPermissions(item).length"
                                            class="flex flex-wrap gap-1.5 max-w-md"
                                        >
                                            <span
                                                v-for="url in getRoleUrlPermissions(item).slice(0, 5)"
                                                :key="url"
                                                class="px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-100 text-[9px] font-black text-blue-500 uppercase tracking-wider"
                                            >
                                                {{ getUrlLabel(url) }}
                                            </span>

                                            <span
                                                v-if="getRoleUrlPermissions(item).length > 5"
                                                class="px-2.5 py-1 rounded-lg bg-slate-100 text-[9px] font-black text-slate-500 uppercase tracking-wider"
                                            >
                                                +{{ getRoleUrlPermissions(item).length - 5 }} URL
                                            </span>
                                        </div>

                                        <span
                                            v-else
                                            class="text-[10px] font-black text-rose-400 uppercase tracking-widest"
                                        >
                                            Tidak ada akses
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10"
                                        >
                                            {{ item.is_active ? 'Active' : 'Offline' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button
                                                @click="openEdit(item)"
                                                class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors text-[11px] font-black uppercase"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                @click="confirmDelete(item.id)"
                                                class="p-2 text-slate-300 hover:text-rose-500 transition-colors text-[11px] font-black uppercase"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                        No Data <span class="text-[#2DD4BF]">Role</span> Found
                                    </h4>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ roles.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ roles.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ roles.total || 0 }}</span> Entries
                    </div>

                    <nav v-if="roles.links && roles.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in roles.links" :key="k">
                            <div
                                v-if="link.url === null"
                                class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter"
                                v-html="link.label"
                            />

                            <Link
                                v-else
                                :href="link.url"
                                class="px-3 py-2 text-[10px] font-black rounded-xl transition-all duration-200 border uppercase tracking-tighter"
                                :class="{
                                    'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105 z-10': link.active,
                                    'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active
                                }"
                                v-html="link.label"
                                preserve-scroll
                            />
                        </template>
                    </nav>
                </div>
            </div>
        </div>

        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-sm p-8 shadow-2xl text-center">
                <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter mb-2">
                    Confirm <span class="text-rose-500">Delete</span>
                </h3>

                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">
                    Data role yang dihapus tidak dapat dikembalikan.
                </p>

                <div class="flex gap-3">
                    <button
                        @click="showDeleteModal = false"
                        :disabled="isDeleting"
                        class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest"
                    >
                        Batal
                    </button>

                    <button
                        @click="executeDelete"
                        :disabled="isDeleting"
                        class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Ya, Hapus' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        >
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-2xl p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4">
                    <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter">
                        {{ isEdit ? 'Modify' : 'Register' }} <span class="text-[#2DD4BF]">Role</span>
                    </h3>

                    <button
                        @click="closeModal"
                        class="text-slate-300 hover:text-rose-500 transition-colors uppercase text-[10px] font-black tracking-widest"
                    >
                        Close
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Nama Role
                        </label>

                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                        />

                        <div v-if="form.errors.name" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                            Deskripsi
                        </label>

                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 resize-none"
                        ></textarea>

                        <div v-if="form.errors.description" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                            {{ form.errors.description }}
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Hak Akses Menu WhatsApp
                            </label>

                            <span class="text-[9px] font-black text-[#2DD4BF] uppercase tracking-widest">
                                {{ form.whatsapp_menu_keys.length }} Menu Dipilih
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 bg-slate-50 rounded-2xl p-4 border border-slate-100">
                            <label
                                v-for="menu in menuOptions"
                                :key="menu.key"
                                class="flex items-center gap-3 p-3 rounded-xl bg-white border border-slate-100 cursor-pointer hover:border-[#2DD4BF]/40 transition-all"
                                :class="form.whatsapp_menu_keys.includes(menu.key) ? 'border-[#2DD4BF] bg-[#2DD4BF]/5' : ''"
                            >
                                <input
                                    type="checkbox"
                                    :value="menu.key"
                                    v-model="form.whatsapp_menu_keys"
                                    class="rounded border-slate-300 text-[#2DD4BF] focus:ring-[#2DD4BF]"
                                />

                                <div>
                                    <div class="text-[11px] font-black text-slate-700 uppercase">
                                        {{ menu.label }}
                                    </div>
                                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                                        {{ menu.key }}
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div v-if="form.errors.whatsapp_menu_keys" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                            {{ form.errors.whatsapp_menu_keys }}
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Hak Akses Sidebar / URL
                            </label>

                            <span class="text-[9px] font-black text-blue-500 uppercase tracking-widest">
                                {{ form.url_permissions.length }} URL Dipilih
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 bg-slate-50 rounded-2xl p-4 border border-slate-100 max-h-72 overflow-y-auto">
                            <label
                                v-for="item in urlOptions"
                                :key="item.url"
                                class="flex items-center gap-3 p-3 rounded-xl bg-white border border-slate-100 cursor-pointer hover:border-blue-300 transition-all"
                                :class="form.url_permissions.includes(item.url) ? 'border-blue-400 bg-blue-50' : ''"
                            >
                                <input
                                    type="checkbox"
                                    :value="item.url"
                                    v-model="form.url_permissions"
                                    class="rounded border-slate-300 text-blue-500 focus:ring-blue-500"
                                />

                                <div>
                                    <div class="text-[11px] font-black text-slate-700 uppercase">
                                        {{ item.label }}
                                    </div>
                                    <div class="text-[9px] font-bold text-slate-400 tracking-widest">
                                        {{ item.url }}
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div v-if="form.errors.url_permissions" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                            {{ form.errors.url_permissions }}
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Hak Akses Aksi
                            </label>

                            <span class="text-[9px] font-black text-rose-500 uppercase tracking-widest">
                                {{ form.action_permissions.length }} Aksi Dipilih
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 bg-slate-50 rounded-2xl p-4 border border-slate-100">
                            <label
                                v-for="item in actionPermissions"
                                :key="item.key"
                                class="flex items-center gap-3 p-3 rounded-xl bg-white border border-slate-100 cursor-pointer hover:border-rose-300 transition-all"
                                :class="form.action_permissions.includes(item.key) ? 'border-rose-400 bg-rose-50' : ''"
                            >
                                <input
                                    type="checkbox"
                                    :value="item.key"
                                    v-model="form.action_permissions"
                                    class="rounded border-slate-300 text-rose-500 focus:ring-rose-500"
                                />

                                <div>
                                    <div class="text-[11px] font-black text-slate-700 uppercase">
                                        {{ item.label }}
                                    </div>
                                    <div class="text-[9px] font-bold text-slate-400 tracking-widest">
                                        {{ item.key }}
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">
                            Active Role Access
                        </span>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                            <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]"></div>
                        </label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#26bba8] transition-all disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Execute Data' }}
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>