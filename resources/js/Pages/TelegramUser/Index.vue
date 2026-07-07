<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    telegramUsers: Object,
    users: Array,
    filters: Object,
    flash: Object,
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const showDeleteModal = ref(false);
const idToDelete = ref(null);
const isDeleting = ref(false);

const form = useForm({
    user_id: '',
    telegram_chat_id: '',
    telegram_user_id: '',
    telegram_username: '',
    telegram_first_name: '',
    telegram_last_name: '',
    is_active: true,
});

watch(search, (value) => {
    router.get(
        route('telegram-users.index'),
        { search: value, page: 1 },
        {
            preserveState: true,
            replace: true,
        }
    );
});

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;

    form.reset();
    form.clearErrors();

    form.user_id = '';
    form.telegram_chat_id = '';
    form.telegram_user_id = '';
    form.telegram_username = '';
    form.telegram_first_name = '';
    form.telegram_last_name = '';
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;

    form.clearErrors();

    form.user_id = item.user_id || '';
    form.telegram_chat_id = item.telegram_chat_id || '';
    form.telegram_user_id = item.telegram_user_id || '';
    form.telegram_username = item.telegram_username || '';
    form.telegram_first_name = item.telegram_first_name || '';
    form.telegram_last_name = item.telegram_last_name || '';
    form.is_active = Boolean(item.is_active);

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('telegram-users.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('telegram-users.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const confirmDelete = (id) => {
    idToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    isDeleting.value = true;

    router.delete(route('telegram-users.destroy', idToDelete.value), {
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
    <Head title="Telegram User" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Telegram <span class="text-[#2DD4BF]">User</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="relative flex-1 max-w-sm">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari telegram user..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 placeholder:text-slate-400"
                    />
                </div>

                <button
                    @click="openCreate"
                    class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all flex items-center shadow-lg shadow-black/5">
                    <span class="mr-2 text-sm">+</span> Tambah Telegram User
                </button>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">User</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Chat ID</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Telegram User ID</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Username</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nama Telegram</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="telegramUsers.data.length > 0">
                                <tr v-for="item in telegramUsers.data" :key="item.id"
                                    class="group hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-black text-[#1E293B] uppercase">
                                            {{ item.user?.name || '-' }}
                                        </div>
                                        <div class="text-[10px] font-bold text-slate-400">
                                            {{ item.user?.phone || item.user?.email || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-[#2DD4BF]">
                                        {{ item.telegram_chat_id }}
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600">
                                        {{ item.telegram_user_id }}
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600">
                                        {{ item.telegram_username ? '@' + item.telegram_username : '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase">
                                        {{ item.telegram_first_name || '-' }} {{ item.telegram_last_name || '' }}
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            :class="item.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest italic border border-current/10">
                                            {{ item.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button @click="openEdit(item)"
                                                class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors">
                                                Edit
                                            </button>

                                            <button @click="confirmDelete(item.id)"
                                                class="p-2 text-slate-300 hover:text-rose-500 transition-colors">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic">
                                        No Data <span class="text-[#2DD4BF]">Telegram User</span> Found
                                    </h4>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ telegramUsers.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ telegramUsers.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ telegramUsers.total || 0 }}</span> Entries
                    </div>

                    <nav v-if="telegramUsers.links && telegramUsers.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in telegramUsers.links" :key="k">
                            <div v-if="link.url === null"
                                class="px-3 py-2 text-[10px] font-black text-slate-300 border border-slate-100 rounded-xl bg-white/50 cursor-not-allowed uppercase tracking-tighter"
                                v-html="link.label" />

                            <Link v-else :href="link.url"
                                class="px-3 py-2 text-[10px] font-black rounded-xl transition-all duration-200 border uppercase tracking-tighter"
                                :class="{
                                    'bg-[#1E293B] text-white border-[#1E293B] shadow-lg shadow-black/10 scale-105 z-10': link.active,
                                    'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active
                                }"
                                v-html="link.label"
                                preserve-scroll />
                        </template>
                    </nav>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-sm p-8 shadow-2xl text-center">
                <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter mb-2">
                    Confirm <span class="text-rose-500">Delete</span>
                </h3>

                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-8">
                    Data yang dihapus tidak dapat dikembalikan.
                </p>

                <div class="flex gap-3">
                    <button @click="showDeleteModal = false" :disabled="isDeleting"
                        class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest">
                        Batal
                    </button>

                    <button @click="executeDelete" :disabled="isDeleting"
                        class="flex-1 py-3 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest">
                        {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-xl shadow-2xl relative max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex justify-between items-center p-8 pb-4 border-b border-slate-100">
                    <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter">
                        {{ isEdit ? 'Modify' : 'Register' }}
                        <span class="text-[#2DD4BF]">Telegram User</span>
                    </h3>

                    <button @click="closeModal"
                        class="text-slate-300 hover:text-rose-500 transition-colors uppercase text-[10px] font-black tracking-widest">
                        Close
                    </button>
                </div>

                <div class="overflow-y-auto px-8 pb-8">
                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                User Internal
                            </label>

                            <select v-model="form.user_id"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20">
                                <option value="">Pilih User</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} - {{ user.phone || user.email }}
                                </option>
                            </select>

                            <div v-if="form.errors.user_id" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.user_id }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    Telegram Chat ID
                                </label>

                                <input v-model="form.telegram_chat_id" type="number"
                                    class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />

                                <div v-if="form.errors.telegram_chat_id" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                    {{ form.errors.telegram_chat_id }}
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    Telegram User ID
                                </label>

                                <input v-model="form.telegram_user_id" type="number"
                                    class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />

                                <div v-if="form.errors.telegram_user_id" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                    {{ form.errors.telegram_user_id }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                Username Telegram
                            </label>

                            <input v-model="form.telegram_username" type="text" placeholder="tanpa @"
                                class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />

                            <div v-if="form.errors.telegram_username" class="text-rose-500 text-[10px] font-bold uppercase mt-1">
                                {{ form.errors.telegram_username }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    First Name
                                </label>

                                <input v-model="form.telegram_first_name" type="text"
                                    class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">
                                    Last Name
                                </label>

                                <input v-model="form.telegram_last_name" type="text"
                                    class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between bg-slate-900 p-4 rounded-2xl">
                            <span class="text-[10px] font-black text-white uppercase tracking-widest">
                                Active Telegram Access
                            </span>

                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" v-model="form.is_active" class="peer sr-only" />
                                <div
                                    class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2DD4BF]" />
                            </label>
                        </div>

                        <button type="submit" :disabled="form.processing"
                            class="w-full py-4 bg-[#2DD4BF] text-white rounded-2xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-[#2DD4BF]/20 hover:bg-[#26bba8] transition-all disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Execute Data' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>