<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    billings: Object,
    vendors: Array,
    cabangs: Array,
    filters: Object,
});

const getDefaultPeriod = () => {
    const now = new Date();

    const end = new Date(now.getFullYear(), now.getMonth(), 28);
    const start = new Date(now.getFullYear(), now.getMonth() - 1, 28);

    const formatDate = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');

        return `${y}-${m}-${d}`;
    };

    return {
        start_date: formatDate(start),
        end_date: formatDate(end),
    };
};

const deleteNote = (noteId) => {
    if (!confirm('Hapus catatan ini?')) {
        return;
    }

    router.delete(
        route('summary.scan-notes.delete', noteId),
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const defaultPeriod = getDefaultPeriod();

const search = ref(props.filters.search || '');
const vendor = ref(props.filters.vendor || '');
const cabangId = ref(props.filters.cabang_id || '');

const startDate = ref(
    props.filters.start_date || defaultPeriod.start_date
);

const endDate = ref(
    props.filters.end_date || defaultPeriod.end_date
);

const saveNote = (item) => {
    if (!item.new_note || !item.new_note.trim()) {
        alert('Catatan tidak boleh kosong');
        return;
    }

    router.post(
        route('summary.scan-notes.store'),
        {
            image_scan_id: item.id,
            cabang_id: item.cabang_id,
            note: item.new_note,
        },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};

const formatNumber = (value) => {
    return Number(value || 0).toLocaleString('id-ID');
};

const formatDate = (value) => {
    if (!value) return '-';

    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const applyFilter = () => {
    router.get(
        route('summary.printer-billing'),
        {
            search: search.value,
            vendor: vendor.value,
            cabang_id: cabangId.value,
            start_date: startDate.value,
            end_date: endDate.value,
            month: endDate.value.substring(0, 7),
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
    vendor.value = '';
    cabangId.value = '';
    startDate.value = defaultPeriod.start_date;
    endDate.value = defaultPeriod.end_date;

    router.get(
        route('summary.printer-billing'),
        {
            start_date: startDate.value,
            end_date: endDate.value,
            month: endDate.value.substring(0, 7),
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const printBilling = () => {
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <html>
        <head>
            <title>Billing Meter Printer</title>

            <style>
                body{
                    font-family: Arial, sans-serif;
                    padding:20px;
                }

                h2{
                    text-align:center;
                    margin-bottom:20px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                }

                th,td{
                    border:1px solid #ddd;
                    padding:6px;
                    font-size:11px;
                    vertical-align:top;
                }

                th{
                    background:#f3f4f6;
                }

                .text-right{
                    text-align:right;
                }
            </style>
        </head>

        <body>
            <h2>Billing Meter Printer</h2>

            <p>
                Periode :
                ${startDate.value}
                s/d
                ${endDate.value}
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Mesin</th>
                        <th>Serial Number</th>
                        <th>Vendor</th>
                        <th>Cabang</th>
                        <th>Meter</th>
                        <th>Total Tagihan</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    ${generateBillingRows()}
                </tbody>
            </table>
        </body>
        </html>
    `);

    printWindow.document.close();

    setTimeout(() => {
        printWindow.print();
    }, 500);
};

const generateBillingRows = () => {
    return props.billings.data.map(item => `
        <tr>
            <td>
                ${formatDate(item.created_at)}
            </td>

            <td>
                ${item.master_nama_mesin || item.nama_mesin || '-'}
            </td>

            <td>
                ${item.serial_number || '-'}
            </td>

            <td>
                ${item.nama_vendor || '-'}
            </td>

            <td>
                ${item.kode_cabang || '-'} -
                ${item.nama_cabang || '-'}
            </td>

            <td>
                BW A3 : ${formatNumber(item.bw_a3)}<br>
                BW A4 : ${formatNumber(item.bw_a4)}<br>
                Color A3 : ${formatNumber(item.color_a3)}<br>
                Color A4 : ${formatNumber(item.color_a4)}<br>
                Long BW : ${formatNumber(item.bw_long_sheet)}<br>
                Long Color : ${formatNumber(item.color_long_sheet)}
            </td>

            <td>
                Billing Mesin: ${formatCurrency(item.total_tagihan)}<br>
                Biaya Part: ${formatCurrency(item.billing_detail?.biaya_part ?? item.biaya_part ?? 0)}<br>
                Biaya Maintenance: ${formatCurrency(item.billing_detail?.biaya_maintenance ?? item.biaya_maintenance ?? 0)}<br>
                <hr>
                <b>Total: ${formatCurrency(item.billing_detail?.grand_total ?? item.grand_total ?? (
                    Number(item.total_tagihan || 0) +
                    Number(item.biaya_part || 0) +
                    Number(item.biaya_maintenance || 0)
                ))}</b>
            </td>

            <td>
                <b>${item.master_mesin_id ? 'OK' : 'BELUM MAPPING'}</b>
                <br><br>
                <b>Catatan:</b><br>
                ${
                    item.notes && item.notes.length
                        ? item.notes.map(note => `
                            - ${note.user_name || '-'}: ${note.note || '-'}
                        `).join('<br>')
                        : '-'
                }
            </td>
        </tr>
    `).join('');
};

const sendWa = () => {
    if (!confirm('Kirim file Excel billing printer ini ke semua user Finance?')) {
        return;
    }

    router.post(
        route('summary.printer-billing.send-wa'),
        {
            search: search.value,
            vendor: vendor.value,
            cabang_id: cabangId.value,
            start_date: startDate.value,
            end_date: endDate.value,
            month: endDate.value.substring(0, 7),
        },
        {
            preserveScroll: true,
        }
    );
};
</script>

<template>
    <Head title="Billing Meter Printer" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
                <div>
                    <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                        Billing <span class="text-[#2DD4BF]">Meter Printer</span>
                    </h2>
                    <p class="hidden sm:block mt-1 text-sm text-slate-400 font-medium">
                        Periode billing printer/mesin
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <div class="mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="relative md:col-span-2">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>

                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari serial, mesin, vendor, cabang..."
                            class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                            @keyup.enter="applyFilter"
                        />
                    </div>

                    <input
                        v-model="startDate"
                        type="date"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    />

                    <input
                        v-model="endDate"
                        type="date"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    />                    
                </div>

                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mt-4">
                    <select
                        v-model="vendor"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    >
                        <option value="">Semua Vendor</option>
                        <option
                            v-for="v in vendors"
                            :key="v.id"
                            :value="v.id"
                        >
                            {{ v.kode_vendor }} - {{ v.nama_vendor }}
                        </option>
                    </select>

                    <select
                        v-model="cabangId"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    >
                        <option value="">Semua Cabang</option>
                        <option v-for="c in cabangs" :key="c.id" :value="c.id">
                            {{ c.kode_cabang }} - {{ c.nama_cabang }}
                        </option>
                    </select>

                    <button
                        type="button"
                        @click="applyFilter"
                        class="px-6 py-3 bg-[#2DD4BF] text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:shadow-lg hover:shadow-teal-200 transition-all"
                    >
                        Filter
                    </button>

                    <button
                        type="button"
                        @click="resetFilter"
                        class="px-5 py-3 bg-white border border-slate-200 text-slate-500 rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-50 transition-all"
                    >
                        Reset
                    </button>
                     <button
                        type="button"
                        @click="printBilling"
                        class="px-5 py-3 bg-slate-700 text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-800 transition-all shadow-sm"
                    >
                        Print
                    </button>

                    <button
                        type="button"
                        @click="sendWa"
                        class="px-5 py-3 bg-[#2DD4BF] text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-[#26bba8] transition-all shadow-sm"
                    >
                        Kirim WA Finance
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Foto
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Mesin
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Vendor / Cabang
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Meter
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Subtotal
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Total
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Catatan
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="billings.data.length > 0">
                                <tr
                                    v-for="item in billings.data"
                                    :key="item.id"
                                    class="hover:bg-slate-50/60 transition"
                                >
                                    <td class="px-6 py-4">
                                        <div class="space-y-3">

                                            <div class="flex gap-2 justify-center">
                                                <div>
                                                    <a
                                                        v-if="item.foto_awal"
                                                        :href="'/storage/' + item.foto_awal"
                                                        target="_blank"
                                                    >
                                                        <img
                                                            :src="'/storage/' + item.foto_awal"
                                                            class="w-14 h-14 rounded-xl object-cover border border-slate-200"
                                                        />
                                                    </a>

                                                    <div
                                                        v-else
                                                        class="w-14 h-14 rounded-xl border border-dashed border-slate-200 bg-slate-50 flex items-center justify-center"
                                                    >
                                                        <span class="text-[8px] text-slate-400 text-center leading-tight">
                                                            Belum ada
                                                        </span>
                                                    </div>

                                                    <div class="text-[8px] text-center text-slate-400 mt-1">
                                                        Awal
                                                    </div>
                                                </div>

                                                <div>
                                                    <template v-if="item.foto_akhir">
                                                        <a
                                                            :href="'/storage/' + item.foto_akhir"
                                                            target="_blank"
                                                        >
                                                            <img
                                                                :src="'/storage/' + item.foto_akhir"
                                                                class="w-14 h-14 rounded-xl object-cover border border-slate-200"
                                                            />
                                                        </a>
                                                    </template>

                                                    <template v-else>
                                                        <div class="w-14 h-14 rounded-xl border border-dashed border-slate-200 bg-slate-50 flex items-center justify-center">
                                                            <span class="text-[8px] text-slate-400 text-center leading-tight">
                                                                Belum ada
                                                            </span>
                                                        </div>
                                                    </template>

                                                    <div class="text-[8px] text-center text-slate-400 mt-1">
                                                        Akhir
                                                    </div>
                                                </div>

                                            </div>

                                            <div class="text-center">
                                                <div class="text-[11px] font-black text-[#1E293B]">
                                                    #{{ item.id }}
                                                </div>

                                                <div class="text-[9px] font-bold text-slate-400 uppercase">
                                                    {{ formatDate(item.created_at) }}
                                                </div>

                                                <div class="text-[9px] font-bold text-slate-400 uppercase">
                                                    {{ item.user_name || '-' }}
                                                </div>
                                            </div>

                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-sm font-black text-[#1E293B] uppercase">
                                            {{ item.master_nama_mesin || item.nama_mesin || '-' }}
                                        </div>

                                        <div class="mt-1 inline-block text-[10px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2 py-1 rounded-lg uppercase">
                                            {{ item.serial_number || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-[11px] font-black text-[#1E293B] uppercase">
                                            {{ item.nama_vendor || '-' }}
                                        </div>

                                        <div class="text-[10px] font-bold text-slate-400 uppercase">
                                            {{ item.kode_vendor || '-' }}
                                        </div>

                                        <div class="mt-1 text-[10px] font-black text-slate-500 uppercase">
                                            {{ item.kode_cabang || '-' }} - {{ item.nama_cabang || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A3: {{ formatNumber(item.bw_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A4: {{ formatNumber(item.bw_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A3: {{ formatNumber(item.color_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A4: {{ formatNumber(item.color_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Long BW: {{ formatNumber(item.bw_long_sheet) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Long Color: {{ formatNumber(item.color_long_sheet) }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A3: {{ formatCurrency(item.billing_detail?.biaya_bw_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A4: {{ formatCurrency(item.billing_detail?.biaya_bw_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A3: {{ formatCurrency(item.billing_detail?.biaya_color_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A4: {{ formatCurrency(item.billing_detail?.biaya_color_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Long BW: {{ formatCurrency(item.billing_detail?.biaya_bw_long_sheet) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Long Color: {{ formatCurrency(item.billing_detail?.biaya_color_long_sheet) }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-lg font-black text-emerald-600">
                                            {{ formatCurrency(item.billing_detail?.grand_total ?? item.grand_total ?? (
                                                Number(item.total_tagihan || 0) +
                                                Number(item.biaya_part || 0) +
                                                Number(item.biaya_maintenance || 0)
                                            )) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Billing Mesin: {{ formatCurrency(item.total_tagihan) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Biaya Part: {{ formatCurrency(item.billing_detail?.biaya_part ?? item.biaya_part ?? 0) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Biaya Maintenance: {{ formatCurrency(item.billing_detail?.biaya_maintenance ?? item.biaya_maintenance ?? 0) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Subtotal: {{ formatCurrency(item.billing_detail?.subtotal_sebelum_free) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Free Klik: {{ formatNumber(item.billing_detail?.free_klik) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Min: {{ formatNumber(item.billing_detail?.minimum_charge_click) }}
                                            {{ item.billing_detail?.minimum_charge_size || '' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="space-y-3">
                                            <div class="text-center">
                                                <span
                                                    class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border"
                                                    :class="item.master_mesin_id
                                                        ? 'bg-emerald-50 text-emerald-600 border-emerald-100'
                                                        : 'bg-rose-50 text-rose-600 border-rose-100'"
                                                >
                                                    {{ item.master_mesin_id ? 'OK' : 'Belum Mapping' }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="item.notes && item.notes.length"
                                                class="space-y-1 max-h-24 overflow-y-auto"
                                            >
                                                <div
                                                    v-for="note in item.notes"
                                                    :key="note.id"
                                                    class="bg-slate-50 border border-slate-100 rounded-lg px-2 py-2 text-left"
                                                >
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div>
                                                            <div class="text-[9px] font-bold text-slate-600">
                                                                {{ note.user_name }}
                                                            </div>

                                                            <div class="text-[10px] text-slate-500">
                                                                {{ note.note }}
                                                            </div>
                                                        </div>

                                                        <button
                                                            type="button"
                                                            @click="deleteNote(note.id)"
                                                            class="text-red-500 hover:text-red-700 text-[10px] font-black"
                                                            title="Hapus"
                                                        >
                                                            ✕
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <input
                                                v-model="item.new_note"
                                                type="text"
                                                placeholder="Tambah catatan..."
                                                class="w-full text-[10px] border border-slate-200 rounded-lg px-3 py-2 focus:border-[#2DD4BF] focus:ring-0"
                                            />

                                            <button
                                                type="button"
                                                @click="saveNote(item)"
                                                class="w-full px-3 py-2 bg-[#2DD4BF] text-white rounded-lg text-[9px] font-black uppercase"
                                            >
                                                Simpan
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-else>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <h4 class="text-[13px] font-black text-[#1E293B] uppercase italic tracking-tighter">
                                        Belum ada data billing printer
                                    </h4>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 bg-slate-50/80 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        Showing
                        <span class="text-[#1E293B]">{{ billings.from || 0 }}</span>
                        to
                        <span class="text-[#1E293B]">{{ billings.to || 0 }}</span>
                        of
                        <span class="text-[#2DD4BF]">{{ billings.total || 0 }}</span>
                        Billings
                    </div>

                    <nav v-if="billings.links && billings.links.length > 0" class="flex flex-wrap gap-1.5">
                        <template v-for="(link, k) in billings.links" :key="k">
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
                                    'bg-white text-slate-600 border-slate-200 hover:border-[#2DD4BF] hover:text-[#2DD4BF]': !link.active,
                                }"
                                v-html="link.label"
                                preserve-scroll
                            />
                        </template>
                    </nav>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>