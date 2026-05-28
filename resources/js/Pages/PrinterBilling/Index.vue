<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    billings: Object,
    vendors: Array,
    cabangs: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const vendor = ref(props.filters.vendor || '');
const billingStatus = ref(props.filters.billing_status || '');
const cabangId = ref(props.filters.cabang_id || '');

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

watch([search, vendor, billingStatus, cabangId], ([searchValue, vendorValue, statusValue, cabangValue]) => {
    router.get(
        route('printer-billing.index'),
        {
            search: searchValue,
            vendor: vendorValue,
            billing_status: statusValue,
            cabang_id: cabangValue,
            page: 1,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
});
</script>

<template>
    <Head title="Billing Meter Printer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-[#1E293B] tracking-tight">
                Billing <span class="text-[#2DD4BF]">Meter Printer</span>
            </h2>
        </template>

        <div class="space-y-6">
            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari serial / mesin / vendor / cabang..."
                        class="bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-[#2DD4BF]/20"
                    />

                    <select
                        v-model="vendor"
                        class="bg-slate-50 border-none rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-[#2DD4BF]/20"
                    >
                        <option value="">Semua Vendor</option>
                        <option v-for="v in vendors" :key="v" :value="v">
                            {{ v }}
                        </option>
                    </select>

                    <select
                        v-model="cabangId"
                        class="bg-slate-50 border-none rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-[#2DD4BF]/20"
                    >
                        <option value="">Semua Cabang</option>
                        <option v-for="c in cabangs" :key="c.id" :value="c.id">
                            {{ c.nama_cabang }}
                        </option>
                    </select>

                    <select
                        v-model="billingStatus"
                        class="bg-slate-50 border-none rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-[#2DD4BF]/20"
                    >
                        <option value="">Semua Status</option>
                        <option value="OK">OK</option>
                        <option value="MASTER_MESIN_TIDAK_DITEMUKAN">
                            Master Tidak Ditemukan
                        </option>
                    </select>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Scan
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
                                    Status
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-50">
                            <template v-if="billings.data.length > 0">
                                <tr
                                    v-for="item in billings.data"
                                    :key="item.scan_id"
                                    class="hover:bg-slate-50/60 transition"
                                >
                                    <td class="px-6 py-4">
                                        <div class="flex gap-3 items-center">
                                            <img
                                                :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'"
                                                class="w-14 h-14 rounded-xl object-cover border border-slate-200"
                                            />

                                            <div>
                                                <div class="text-[11px] font-black text-[#1E293B]">
                                                    #{{ item.scan_id }}
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
                                            {{ item.nama_mesin_master || item.nama_mesin_scan || '-' }}
                                        </div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase">
                                            Scan: {{ item.nama_mesin_scan || '-' }}
                                        </div>
                                        <div class="mt-1 inline-block text-[10px] font-black text-[#2DD4BF] bg-[#2DD4BF]/5 border border-[#2DD4BF]/10 px-2 py-1 rounded-lg uppercase">
                                            {{ item.serial_number || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-[11px] font-black text-[#1E293B] uppercase">
                                            {{ item.vendor_name || item.vendor || '-' }}
                                        </div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase">
                                            {{ item.kode_vendor || '-' }}
                                        </div>
                                        <div class="mt-1 text-[10px] font-black text-slate-500 uppercase">
                                            {{ item.nama_cabang || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A3: {{ formatNumber(item.total_bw_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A4: {{ formatNumber(item.total_bw_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A3: {{ formatNumber(item.total_color_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A4: {{ formatNumber(item.total_color_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Long: {{ formatNumber(item.total_long_sheet) }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A3: {{ formatCurrency(item.subtotal_bw_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            BW A4: {{ formatCurrency(item.subtotal_bw_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A3: {{ formatCurrency(item.subtotal_color_a3) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Color A4: {{ formatCurrency(item.subtotal_color_a4) }}
                                        </div>
                                        <div class="text-[10px] font-black text-slate-600">
                                            Long: {{ formatCurrency(item.subtotal_long_sheet) }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-right">
                                        <div class="text-lg font-black text-emerald-600">
                                            {{ formatCurrency(item.total_tagihan) }}
                                        </div>

                                        <div class="text-[9px] font-bold text-slate-400 uppercase">
                                            Free Klik: {{ item.free_klik_percent || 0 }}%
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border"
                                            :class="item.billing_status === 'OK'
                                                ? 'bg-emerald-50 text-emerald-600 border-emerald-100'
                                                : 'bg-rose-50 text-rose-600 border-rose-100'"
                                        >
                                            {{ item.billing_status }}
                                        </span>
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