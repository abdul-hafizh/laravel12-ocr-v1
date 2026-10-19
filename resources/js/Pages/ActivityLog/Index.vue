<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: Object,
    modules: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const module = ref(props.filters?.module || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

const selectedLog = ref(null);

const applyFilter = () => {
    router.get(
        route('log-activity.index'),
        {
            search: search.value,
            module: module.value,
            start_date: startDate.value,
            end_date: endDate.value,
            page: 1,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const resetFilter = () => {
    search.value = '';
    module.value = '';
    startDate.value = '';
    endDate.value = '';

    router.get(
        route('log-activity.index'),
        {},
        {
            preserveState: true,
            replace: true,
        }
    );
};

const formatDateTime = (date) => {
    if (!date) return '-';

    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const detailRows = (log) => {
    const p = log?.properties || {};

    return [
        { label: 'Menu', value: log?.module_label },
        { label: 'ID Data', value: log?.subject_id },
        { label: 'Cabang', value: p.cabang },
        { label: 'Diupload Oleh', value: p.uploaded_by },
        { label: 'Tanggal Upload', value: p.uploaded_at ? formatDateTime(p.uploaded_at) : null },
        { label: 'Nama File', value: p.original_filename },
        { label: 'Status', value: p.status },
        { label: 'IP Address', value: log?.ip_address },
        { label: 'User Agent', value: log?.user_agent },
    ];
};

const formatJson = (value) => {
    if (value === null || value === undefined) return '-';

    return JSON.stringify(value, null, 2);
};
</script>

<template>
    <Head title="Log Activity" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Log <span class="text-[#2DD4BF]">Activity</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <div class="relative md:col-span-2">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>

                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari user, email, keterangan..."
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20 transition-all placeholder:text-slate-400"
                            @keyup.enter="applyFilter"
                        />
                    </div>

                    <select
                        v-model="module"
                        class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold uppercase focus:ring-2 focus:ring-[#2DD4BF]/20"
                    >
                        <option value="">Semua Menu</option>
                        <option
                            v-for="item in modules"
                            :key="item.value"
                            :value="item.value"
                        >
                            {{ item.label }}
                        </option>
                    </select>

                    <input
                        v-model="startDate"
                        type="date"
                        title="Dari tanggal"
                        class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                    />

                    <input
                        v-model="endDate"
                        type="date"
                        title="Sampai tanggal"
                        class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                    />

                    <div class="flex gap-2">
                        <button
                            @click="applyFilter"
                            class="px-5 py-2.5 bg-[#1E293B] text-white rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-[#2DD4BF] transition-all shadow-lg shadow-black/5"
                        >
                            Filter
                        </button>

                        <button
                            @click="resetFilter"
                            class="px-5 py-2.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-[0.15em] hover:bg-slate-200 transition-all"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Waktu
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    User
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Aktivitas
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Menu
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Keterangan
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Detail
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="logs.data.length > 0">
                                <tr
                                    v-for="log in logs.data"
                                    :key="log.id"
                                    class="group hover:bg-slate-50/50 transition-colors"
                                >
                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase whitespace-nowrap">
                                        {{ formatDateTime(log.created_at) }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-sm font-black text-[#1E293B] uppercase tracking-tight">
                                            {{ log.user_name || '-' }}
                                        </div>
                                        <div class="text-[10px] font-black text-[#2DD4BF] tracking-widest">
                                            {{ log.user_email || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border border-current/10 bg-rose-50 text-rose-600">
                                            {{ log.action }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-black text-slate-600 uppercase whitespace-nowrap">
                                        {{ log.module_label }}
                                    </td>

                                    <td class="px-6 py-4 text-[11px] font-bold text-slate-600">
                                        {{ log.description || '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <button
                                            @click="selectedLog = log"
                                            class="p-2 text-slate-300 hover:text-[#2DD4BF] transition-colors"
                                            title="Lihat detail"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                    stroke-width="2.5"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </div>

                                        <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                            No <span class="text-[#2DD4BF]">Activity</span> Found
                                        </h4>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing <span class="text-[#1E293B]">{{ logs.from || 0 }}</span>
                        to <span class="text-[#1E293B]">{{ logs.to || 0 }}</span>
                        of <span class="text-[#2DD4BF]">{{ logs.total || 0 }}</span> Entries
                    </div>

                    <nav v-if="logs.links && logs.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in logs.links" :key="k">
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

                    <div v-else class="text-[10px] font-black text-slate-300 uppercase italic">
                        No additional pages
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="selectedLog"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            @click.self="selectedLog = null"
        >
            <div class="bg-white rounded-[2rem] border border-slate-200 w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl">
                <div class="px-8 pt-8 pb-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-black text-[#1E293B] uppercase italic tracking-tighter">
                            Detail <span class="text-[#2DD4BF]">Log</span>
                        </h3>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                            {{ selectedLog.user_name || '-' }} &middot; {{ formatDateTime(selectedLog.created_at) }}
                        </p>
                    </div>

                    <button
                        @click="selectedLog = null"
                        class="p-2 text-slate-300 hover:text-slate-500 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <div class="px-8 pb-8 overflow-y-auto space-y-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div
                            v-for="row in detailRows(selectedLog)"
                            :key="row.label"
                            class="bg-slate-50 rounded-xl px-4 py-3"
                        >
                            <dt class="text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                {{ row.label }}
                            </dt>
                            <dd class="text-[12px] font-bold text-[#1E293B] break-words">
                                {{ row.value ?? '-' }}
                            </dd>
                        </div>
                    </dl>

                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">
                            Data Hasil Scan Sebelum Dihapus
                        </div>
                        <pre class="bg-slate-900 text-slate-100 text-[11px] rounded-xl p-4 overflow-x-auto max-h-72">{{ formatJson(selectedLog.properties?.analysis_result) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
