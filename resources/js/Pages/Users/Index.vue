<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    users: Object,
    roles: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const modalMode = ref('create');
const selectedUser = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    employee_id: '',
    phone: '',
    gender: '',
    role_id: null,
    is_active: true,
});

watch(search, (value) => {
    router.get(
        route('users-management.index'),
        { search: value, page: 1 },
        { preserveState: true, replace: true }
    );
});

const syncUsers = () => {
    if (!confirm('Synchron user dari database eksternal?')) return;

    router.post(route('users-management.sync'), {}, {
        preserveScroll: true,
    });
};

const formatPhone = () => {
    if (!form.phone) return;

    let value = form.phone.replace(/\D/g, '');

    if (value.startsWith('08')) {
        value = '628' + value.slice(2);
    }

    if (value.startsWith('8')) {
        value = '62' + value;
    }

    form.phone = value;
};

const openCreate = () => {
    modalMode.value = 'create';
    selectedUser.value = null;

    form.reset();
    form.clearErrors();

    form.name = '';
    form.email = '';
    form.password = '';
    form.employee_id = '';
    form.phone = '';
    form.gender = '';
    form.role_id = null;
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (user) => {
    modalMode.value = 'edit';
    selectedUser.value = user;

    form.reset();
    form.clearErrors();

    form.name = user.name || '';
    form.email = user.email || '';
    form.password = '';
    form.employee_id = user.employee_id || '';
    form.phone = user.phone || '';
    form.gender = user.gender || '';
    form.role_id = user.role_id || null;
    form.is_active = Boolean(user.is_active);

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedUser.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (modalMode.value === 'create') {
        form.post(route('users-management.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.put(route('users-management.update', selectedUser.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const deleteUser = (user) => {
    if (!confirm(`Hapus user ${user.name}?`)) return;

    router.delete(route('users-management.destroy', user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Manajemen User" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Manajemen <span class="text-[#2DD4BF]">User</span>
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
                        placeholder="Cari user..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all placeholder:text-slate-400"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="syncUsers"
                        class="px-5 py-3 bg-[#1E293B] text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-black/10 hover:bg-slate-700 transition-all"
                    >
                        Sync User
                    </button>

                    <button
                        @click="openCreate"
                        class="px-5 py-3 bg-[#2DD4BF] text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#26bba8] transition-all"
                    >
                        Tambah User
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nama</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Email</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Employee ID</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Phone</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Gender</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Role</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="users.data.length > 0">
                                <tr v-for="item in users.data" :key="item.id" class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 text-sm font-bold text-[#1E293B] uppercase">
                                        {{ item.name }}
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-bold text-slate-500">
                                        {{ item.email }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="text-[11px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2.5 py-1 rounded-lg uppercase">
                                            {{ item.employee_id || '-' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600">
                                        {{ item.phone || '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase">
                                        {{ item.gender || '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase">
                                        {{ item.role?.name || 'Tanpa Role' }}
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10"
                                        >
                                            {{ item.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                @click="openEdit(item)"
                                                class="px-3 py-2 text-[10px] font-black uppercase tracking-widest text-[#2DD4BF] bg-[#2DD4BF]/5 rounded-xl hover:bg-[#2DD4BF] hover:text-white transition-all"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                @click="deleteUser(item)"
                                                class="px-3 py-2 text-[10px] font-black uppercase tracking-widest text-rose-500 bg-rose-50 rounded-xl hover:bg-rose-500 hover:text-white transition-all"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="8" class="px-6 py-20 text-center">
                                    <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                        No Data <span class="text-[#2DD4BF]">User</span> Found
                                    </h4>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ users.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ users.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ users.total || 0 }}</span> Entries
                    </div>

                    <nav v-if="users.links && users.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in users.links" :key="k">
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

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-3xl p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4">
                    <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter">
                        {{ modalMode === 'create' ? 'Tambah' : 'Edit' }}
                        <span class="text-[#2DD4BF]">User</span>
                    </h3>

                    <button @click="closeModal" class="text-slate-300 hover:text-rose-500 transition-colors uppercase text-[10px] font-black tracking-widest">
                        Close
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nama</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                                placeholder="Nama user"
                            />
                            <div v-if="form.errors.name" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Email</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                                placeholder="Email user"
                            />
                            <div v-if="form.errors.email" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Password
                                <span v-if="modalMode === 'edit'" class="text-slate-300 normal-case">(kosongkan jika tidak diganti)</span>
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                                placeholder="Password"
                            />
                            <div v-if="form.errors.password" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.password }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Employee ID</label>
                            <input
                                v-model="form.employee_id"
                                type="text"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                                placeholder="Employee ID"
                            />
                            <div v-if="form.errors.employee_id" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.employee_id }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Phone</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                @input="formatPhone"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold"
                                placeholder="628xxxxxxxxxx"
                            />
                            <div v-if="form.errors.phone" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.phone }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Gender</label>
                            <select
                                v-model="form.gender"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                            >
                                <option value="">Pilih Gender</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                            <div v-if="form.errors.gender" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.gender }}
                            </div>
                        </div>

                        <div class="space-y-1.5 md:col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Role User</label>
                            <select
                                v-model="form.role_id"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                            >
                                <option :value="null">Tanpa Role</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.role_id" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.role_id }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                        <span class="text-[10px] font-black text-white uppercase tracking-widest">Active User Access</span>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                            <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]"></div>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing" class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#26bba8] transition-all disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : modalMode === 'create' ? 'Simpan User' : 'Update User' }}
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>