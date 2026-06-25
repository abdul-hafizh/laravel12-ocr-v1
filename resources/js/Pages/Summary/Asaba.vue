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

const defaultPeriod = getDefaultPeriod();

const search = ref(props.filters.search || '');
const vendor = ref(props.filters.vendor || '');
const cabangId = ref(props.filters.cabang_id || '');

const startDate = ref(props.filters.start_date || defaultPeriod.start_date);
const endDate = ref(props.filters.end_date || defaultPeriod.end_date);

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
        route('summary.asaba'),
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
        route('summary.asaba'),
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
                    Total: ${formatNumber(item.total_counter)}<br>
                    Printer: ${formatNumber(item.printer_counter)}<br>
                    Copy: ${formatNumber(item.copy_counter)}<br>
                    Full Color: ${formatNumber(item.full_color_counter)}<br>
                    Single Color: ${formatNumber(item.single_color_counter)}<br>
                    Black: ${formatNumber(item.black_counter)}
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
            <title>Billing Counter Asaba</title>
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
            <h2>Billing Counter Asaba</h2>
            <p>Periode : ${startDate.value} s/d ${endDate.value}</p>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Mesin</th>
                        <th>Serial Number</th>
                        <th>Vendor</th>
                        <th>Cabang</th>
                        <th>Counter</th>
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
</script>

<template>
    <Head title="Billing Counter Asaba" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
                <div>
                    <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                        Billing <span class="text-[#2DD4BF]">Counter Asaba</span>
                    </h2>
                    <p class="text-sm text-slate-400 font-medium mt-1">
                        Periode billing mesin Develop / Asaba
                    </p>
                </div>

                <button
                    type="button"
                    @click="printBilling"
                    class="px-5 py-3 bg-slate-700 text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-800 transition-all shadow-sm"
                >
                    Print
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <div class="mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <select
                        v-model="vendor"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    >
                        <option value="">Semua Vendor</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">
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
                                    Counter Scan
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
                                    v-for="item in billings.data"
                                    :key="item.id"
                                    class="hover:bg-slate-50/60 transition"
                                >
                                    <td class="px-6 py-4">
                                        <div class="space-y-3">
                                            <div class="flex gap-2 justify-center">
                                                <div>
                                                    <img
                                                        :src="item.foto_awal ? '/storage/' + item.foto_awal : '/no-image.png'"
                                                        class="w-14 h-14 rounded-xl object-cover border border-slate-200"
                                                    />
                                                    <div class="text-[8px] text-center text-slate-400 mt-1">
                                                        Awal
                                                    </div>
                                                </div>

                                                <div>
                                                    <img
                                                        :src="item.foto_akhir ? '/storage/' + item.foto_akhir : '/no-image.png'"
                                                        class="w-14 h-14 rounded-xl object-cover border border-slate-200"
                                                    />
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
                                            Total: {{ formatNumber(item.total_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Printer: {{ formatNumber(item.printer_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Copy: {{ formatNumber(item.copy_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Scan: {{ formatNumber(item.scan_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Feed: {{ formatNumber(item.feed_paper_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Output: {{ formatNumber(item.output_paper_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Full Color: {{ formatNumber(item.full_color_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Single Color: {{ formatNumber(item.single_color_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Black: {{ formatNumber(item.black_counter) }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[10px] font-black text-slate-600">
                                            Total: {{ formatNumber(item.usage_total_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Printer: {{ formatNumber(item.usage_printer_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Copy: {{ formatNumber(item.usage_copy_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Scan: {{ formatNumber(item.usage_scan_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Full Color: {{ formatNumber(item.usage_full_color_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Single Color: {{ formatNumber(item.usage_single_color_counter) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Black: {{ formatNumber(item.usage_black_counter) }}
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
                                        Belum ada data billing Asaba
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