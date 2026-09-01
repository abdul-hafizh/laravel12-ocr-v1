<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    billings: Object,
    cabangs: Array,
    filters: Object,
});

const getDefaultMonth = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const search = ref(props.filters.search || '');
const cabangId = ref(props.filters.cabang_id || '');
const month = ref(props.filters.month || getDefaultMonth());

const monthLabelFormatter = new Intl.DateTimeFormat('id-ID', {
    month: 'long',
    year: 'numeric',
});

const monthDisplayLabel = () => {
    if (!month.value) return '-';

    const [y, m] = month.value.split('-').map(Number);
    if (!y || !m) return '-';

    return monthLabelFormatter.format(new Date(y, m - 1, 1));
};

const periodeLabel = () => {
    if (!props.filters.start_date || !props.filters.end_date) return '-';

    return `${props.filters.start_date} s/d ${props.filters.end_date}`;
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
        route('summary.astra'),
        {
            search: search.value,
            cabang_id: cabangId.value,
            month: month.value,
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
    cabangId.value = '';
    month.value = getDefaultMonth();

    router.get(
        route('summary.astra'),
        {
            month: month.value,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

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

const deleteNote = (noteId) => {
    if (!confirm('Hapus catatan ini?')) return;

    router.delete(
        route('summary.scan-notes.delete', noteId),
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const generateBillingRows = () => {
    return props.billings.data.map(item => {
        const biayaPart = Number(item.biaya_part || 0);
        const biayaMaintenance = Number(item.biaya_maintenance || 0);
        const billingMesin = Number(item.total_tagihan || 0);
        const grandTotal = billingMesin + biayaPart + biayaMaintenance;

        return `
            <tr>
                <td>${formatDate(item.created_at)}</td>
                <td>${item.master_nama_mesin || item.nama_mesin || '-'}</td>
                <td>${item.serial_number || '-'}</td>
                <td>${item.nama_vendor || '-'}</td>
                <td>${item.kode_cabang || '-'} - ${item.nama_cabang || '-'}</td>
                <td>
                    Total: ${formatNumber(item.total_impressions)}<br>
                    Color: ${formatNumber(item.color_impressions)}<br>
                    Color Large: ${formatNumber(item.color_large_impressions)}<br>
                    Black: ${formatNumber(item.black_impressions)}
                </td>
                <td class="text-right">
                    Billing Mesin: ${formatCurrency(billingMesin)}<br>
                    Biaya Part: ${formatCurrency(biayaPart)}<br>
                    Biaya Maintenance: ${formatCurrency(biayaMaintenance)}<br>
                    <hr>
                    <b>Grand Total: ${formatCurrency(grandTotal)}</b>
                </td>
                <td>
                    <b>${item.master_mesin_id ? 'OK' : 'BELUM MAPPING'}</b><br><br>
                    <b>Catatan:</b><br>
                    ${
                        item.notes && item.notes.length
                            ? item.notes.map(note => `- ${note.user_name || '-'}: ${note.note || '-'}`).join('<br>')
                            : '-'
                    }
                </td>
            </tr>
        `;
    }).join('');
};

const printBilling = () => {
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <html>
        <head>
            <title>Billing Astra</title>
            <style>
                body{font-family:Arial,sans-serif;padding:20px;}
                h2{text-align:center;margin-bottom:20px;}
                table{width:100%;border-collapse:collapse;}
                th,td{border:1px solid #ddd;padding:6px;font-size:11px;vertical-align:top;}
                th{background:#f3f4f6;}
                .text-right{text-align:right;}
            </style>
        </head>
        <body>
            <h2>Billing Astra</h2>
            <p>Periode : ${monthDisplayLabel()} (${periodeLabel()})</p>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Mesin</th>
                        <th>Serial Number</th>
                        <th>Vendor</th>
                        <th>Cabang</th>
                        <th>Impressions</th>
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

const generateLaporanRows = () => {
    return props.billings.data.map(item => {
        const biayaBw = Number(item.billing_detail?.biaya_bw ?? 0);
        const biayaColor = Number(item.billing_detail?.biaya_color ?? 0);
        const biayaPart = Number(item.biaya_part ?? 0);
        const biayaMaintenance = Number(item.biaya_maintenance ?? 0);

        const totalDebit = biayaBw + biayaColor + biayaPart + biayaMaintenance;
        const totalKredit = totalDebit;

        return `
            <div class="report-box">
                <div class="report-title">
                    Estimasi Biaya Print ${item.nama_cabang || '-'}
                </div>

                <div style="margin-top:8px;font-size:16px;">
                    Mesin :
                    <b>${item.master_nama_mesin || item.nama_mesin || '-'}</b>
                    (Astra)
                </div>

                <div style="margin-top:4px;font-size:14px;">
                    Serial :
                    <b>${item.serial_number || '-'}</b>
                </div>

                <div class="report-period">
                    ${monthDisplayLabel()}
                </div>

                <table class="journal-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Journal</th>
                            <th style="text-align:right;width:180px;">Debit</th>
                            <th style="text-align:right;width:180px;">Kredit</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>biaya BW</td>
                            <td style="text-align:right;">${formatNumber(biayaBw)}</td>
                            <td></td>
                        </tr>

                        <tr>
                            <td>biaya Color</td>
                            <td style="text-align:right;">${formatNumber(biayaColor)}</td>
                            <td></td>
                        </tr>

                        <tr>
                            <td>biaya part</td>
                            <td style="text-align:right;">${formatNumber(biayaPart)}</td>
                            <td></td>
                        </tr>

                        <tr>
                            <td>biaya maintenance</td>
                            <td style="text-align:right;">${formatNumber(biayaMaintenance)}</td>
                            <td></td>
                        </tr>

                        <tr>
                            <td style="padding-left:80px;">cadangan mesin</td>
                            <td></td>
                            <td style="text-align:right;">${formatNumber(totalKredit)}</td>
                        </tr>

                        <tr class="total-row">
                            <td></td>
                            <td style="text-align:right;">${formatNumber(totalDebit)}</td>
                            <td style="text-align:right;">${formatNumber(totalKredit)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    }).join('');
};

const printLaporan = () => {
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <html>
        <head>
            <title>Laporan Estimasi Biaya Astra</title>

            <style>
                body {
                    font-family: Arial, sans-serif;
                    padding: 24px;
                    color: #111827;
                }

                .report-box {
                    border: 1px solid #333;
                    padding: 32px 48px;
                    margin-bottom: 32px;
                    page-break-inside: avoid;
                }

                .report-title {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 6px;
                }

                .report-period {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 40px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                }

                th {
                    font-size: 16px;
                    text-align: left;
                    padding-bottom: 12px;
                    text-decoration: underline;
                    font-weight: 400;
                }

                td {
                    font-size: 16px;
                    padding: 5px 0;
                }

                td:last-child {
                    text-align: right;
                    width: 220px;
                }

                .total-row td {
                    padding-top: 16px;
                    border-top: 1px solid #333;
                    font-weight: 600;
                }

                @media print {
                    .report-box {
                        page-break-inside: avoid;
                    }
                }
            </style>
        </head>

        <body>
            ${generateLaporanRows()}
        </body>
        </html>
    `);

    printWindow.document.close();

    setTimeout(() => {
        printWindow.print();
    }, 500);
};
</script>

<template>
    <Head title="Billing Mesin Astra" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
                <div>
                    <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                        Billing <span class="text-[#2DD4BF]">Mesin Astra</span>
                    </h2>
                    <p class="hidden sm:block mt-1 text-sm text-slate-400 font-medium">
                        Periode billing mesin Astra (Astragraphia)
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <div class="mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="relative md:col-span-2">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari serial, mesin, vendor, cabang..."
                            class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                            @keyup.enter="applyFilter"
                        />
                    </div>

                    <input
                        v-model="month"
                        type="month"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
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
                        @click="printLaporan"
                        class="px-5 py-3 bg-[#2DD4BF] text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-[#26bba8] transition-all shadow-sm"
                    >
                        Laporan
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    No
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Foto
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Mesin
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Cabang
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Impressions
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Pemakaian
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Billing
                                </th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    Catatan
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="billings.data.length > 0">
                                <tr
                                    v-for="(item, index) in billings.data"
                                    :key="item.id"
                                    class="hover:bg-slate-50/60 transition"
                                >
                                    <td class="px-6 py-4 text-sm font-bold text-slate-500">
                                        {{ (billings.from ?? 1) + index }}
                                    </td>
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
                                            {{ item.nama_vendor || 'Astragraphia' }}
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
                                            Total: {{ formatNumber(item.total_impressions) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color: {{ formatNumber(item.color_impressions) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color Large: {{ formatNumber(item.color_large_impressions) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Black: {{ formatNumber(item.black_impressions) }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[10px] font-black text-slate-600">
                                            Total: {{ formatNumber(item.usage_total_impressions) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color: {{ formatNumber(item.usage_color_impressions) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color Large: {{ formatNumber(item.usage_color_large_impressions) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Black: {{ formatNumber(item.usage_black_impressions) }}
                                        </div>

                                        <div class="mt-2 pt-2 border-t border-slate-100">
                                            <div class="text-[10px] font-black text-emerald-600">
                                                BW Billing: {{ formatNumber(item.usage_bw_billing) }}
                                            </div>
                                            <div class="text-[10px] font-black text-emerald-600">
                                                Color Billing: {{ formatNumber(item.usage_color_billing) }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-lg font-black text-emerald-600">
                                            {{ formatCurrency(
                                                Number(item.total_tagihan || 0) +
                                                Number(item.biaya_part || 0) +
                                                Number(item.biaya_maintenance || 0)
                                            ) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Billing Mesin: {{ formatCurrency(item.total_tagihan) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Biaya Part: {{ formatCurrency(item.biaya_part) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Biaya Maintenance: {{ formatCurrency(item.biaya_maintenance) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            BW: {{ formatCurrency(item.billing_detail?.biaya_bw) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Color: {{ formatCurrency(item.billing_detail?.biaya_color) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Free Klik: {{ formatNumber(item.billing_detail?.free_klik) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Rule: {{ item.billing_rule || '-' }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Source: {{ item.counter_source || '-' }}
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
                                        Belum ada data billing Astra
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
