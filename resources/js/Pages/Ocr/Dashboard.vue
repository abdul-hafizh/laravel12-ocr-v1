<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(false);

const filters = ref({
    tanggal: '',
    bulan: '',
    tahun: '',
    cabang: '',
    user_phone: '',
});

const options = ref({
    cabangs: [],
    user_phones: [],
});

const summary = ref({
    total: 0,
    electricity: 0,
    online_receipt: 0,
    printer: 0,
});

const tables = ref({
    electricity: null,
    online_receipt: null,
    printer: null,
});

const currentPages = ref({
    electricity_page: 1,
    online_receipt_page: 1,
    printer_page: 1,
});

const getDashboardData = async () => {
    loading.value = true;

    try {
        const res = await axios.get('/dashboard-scans', {
            params: {
                ...filters.value,
                ...currentPages.value,
                per_page: 10,
            },
        });

        options.value = res.data.filters ?? {
            cabangs: [],
            user_phones: [],
        };

        summary.value = res.data.summary ?? {
            total: 0,
            electricity: 0,
            online_receipt: 0,
            printer: 0,
        };

        tables.value = res.data.data ?? {
            electricity: null,
            online_receipt: null,
            printer: null,
        };
    } catch (error) {
        console.error('Gagal mengambil dashboard:', error);
    } finally {
        loading.value = false;
    }
};

const applyFilter = () => {
    currentPages.value = {
        electricity_page: 1,
        online_receipt_page: 1,
        printer_page: 1,
    };

    getDashboardData();
};

const resetFilter = () => {
    filters.value = {
        tanggal: '',
        bulan: '',
        tahun: '',
        cabang: '',
        user_phone: '',
    };

    applyFilter();
};

const changePage = (type, page) => {
    if (!page) return;

    if (type === 'electricity') {
        currentPages.value.electricity_page = page;
    }

    if (type === 'online_receipt') {
        currentPages.value.online_receipt_page = page;
    }

    if (type === 'printer') {
        currentPages.value.printer_page = page;
    }

    getDashboardData();
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

const formatNumber = (value) => {
    if (value === null || value === undefined || value === '') return '-';

    if (typeof value === 'number') {
        return value.toLocaleString('id-ID');
    }

    return value;
};

const getAnalysis = (row) => {
    return row?.analysis_result ?? {};
};

const getDataPenting = (row) => {
    const analysis = getAnalysis(row);
    return analysis?.data_penting ?? {};
};

const getImageUrl = (path) => {
    if (!path) return null;

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    return `/storage/${path}`;
};

const getStatusClass = (status) => {
    if (status === 'success') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    }

    if (status === 'failed') {
        return 'bg-rose-50 text-rose-700 ring-rose-200';
    }

    if (status === 'processing') {
        return 'bg-amber-50 text-amber-700 ring-amber-200';
    }

    return 'bg-slate-50 text-slate-600 ring-slate-200';
};

const scanTypeLabels = {
    electricity: 'Token Listrik',
    online_receipt: 'Struk Online',
    printer: 'Mesin Cetak',
};

const scanTypeBadges = {
    electricity: 'bg-yellow-50 text-yellow-700',
    online_receipt: 'bg-blue-50 text-blue-700',
    printer: 'bg-purple-50 text-purple-700',
};

onMounted(() => {
    getDashboardData();
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold text-slate-800">
                    Dashboard Hasil Upload
                </h2>
                <p class="mt-1 text-sm text-slate-400">
                    Pantau hasil OCR berdasarkan tanggal, bulan, tahun, cabang, dan nomor WhatsApp user.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <!-- FILTER -->
            <div class="rounded-3xl border bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-400">
                            Tanggal
                        </label>
                        <input
                            v-model="filters.tanggal"
                            type="number"
                            min="1"
                            max="31"
                            placeholder="Contoh: 15"
                            class="w-full rounded-xl border-slate-200 text-sm"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-400">
                            Bulan
                        </label>
                        <select
                            v-model="filters.bulan"
                            class="w-full rounded-xl border-slate-200 text-sm"
                        >
                            <option value="">Semua Bulan</option>
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-400">
                            Tahun
                        </label>
                        <input
                            v-model="filters.tahun"
                            type="number"
                            placeholder="Contoh: 2026"
                            class="w-full rounded-xl border-slate-200 text-sm"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-400">
                            Cabang
                        </label>
                        <select
                            v-model="filters.cabang"
                            class="w-full rounded-xl border-slate-200 text-sm"
                        >
                            <option value="">Semua Cabang</option>
                            <option
                                v-for="cabang in options.cabangs"
                                :key="cabang.id"
                                :value="cabang.id"
                            >
                                {{ cabang.kode_cabang }} - {{ cabang.nama_cabang }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-400">
                            User Phone
                        </label>
                        <select
                            v-model="filters.user_phone"
                            class="w-full rounded-xl border-slate-200 text-sm"
                        >
                            <option value="">Semua User</option>
                            <option
                                v-for="phone in options.user_phones"
                                :key="phone"
                                :value="phone"
                            >
                                {{ phone }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        @click="applyFilter"
                        class="rounded-xl bg-slate-800 px-5 py-2 text-sm font-bold text-white hover:bg-slate-700"
                    >
                        Filter
                    </button>

                    <button
                        type="button"
                        @click="resetFilter"
                        class="rounded-xl bg-slate-100 px-5 py-2 text-sm font-bold text-slate-600 hover:bg-slate-200"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- SUMMARY -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-3xl border bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase text-slate-400">
                        Total Upload
                    </div>
                    <div class="mt-2 text-3xl font-black text-slate-800">
                        {{ formatNumber(summary.total) }}
                    </div>
                </div>

                <div class="rounded-3xl border bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase text-slate-400">
                        Token Listrik
                    </div>
                    <div class="mt-2 text-3xl font-black text-yellow-600">
                        {{ formatNumber(summary.electricity) }}
                    </div>
                </div>

                <div class="rounded-3xl border bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase text-slate-400">
                        Struk Online
                    </div>
                    <div class="mt-2 text-3xl font-black text-blue-600">
                        {{ formatNumber(summary.online_receipt) }}
                    </div>
                </div>

                <div class="rounded-3xl border bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase text-slate-400">
                        Mesin Cetak
                    </div>
                    <div class="mt-2 text-3xl font-black text-purple-600">
                        {{ formatNumber(summary.printer) }}
                    </div>
                </div>
            </div>

            <div
                v-if="loading"
                class="rounded-3xl bg-white p-10 text-center text-slate-500 shadow-sm"
            >
                Memuat data dashboard...
            </div>

            <template v-else>
                <!-- ELECTRICITY -->
                <div class="rounded-3xl border bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">
                                Token Listrik
                            </h3>
                            <p class="text-sm text-slate-400">
                                Data hasil scan token listrik.
                            </p>
                        </div>

                        <span class="rounded-full bg-yellow-50 px-3 py-1 text-xs font-bold text-yellow-700">
                            {{ formatNumber(tables.electricity?.total ?? 0) }} data
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Cabang</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Tanggal Token</th>
                                    <th class="px-4 py-3">KWH</th>
                                    <th class="px-4 py-3">Nomor Meter</th>
                                    <th class="px-4 py-3">Nomor Token</th>
                                    <th class="px-4 py-3">Gambar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in tables.electricity?.data ?? []"
                                    :key="row.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(row.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-700">
                                            {{ row.user?.name ?? '-' }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ row.user?.phone ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-700">
                                            {{ row.cabang?.nama_cabang ?? '-' }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ row.cabang?.kode_cabang ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-bold ring-1"
                                            :class="getStatusClass(row.status)"
                                        >
                                            {{ row.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ getDataPenting(row).tanggal ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ getDataPenting(row).kwh ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ getDataPenting(row).nomor_meter ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ getDataPenting(row).nomor_token ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            v-if="getImageUrl(row.image_path)"
                                            :href="getImageUrl(row.image_path)"
                                            target="_blank"
                                            class="font-bold text-blue-600 hover:underline"
                                        >
                                            Lihat
                                        </a>
                                        <span v-else>-</span>
                                    </td>
                                </tr>

                                <tr v-if="!tables.electricity?.data?.length">
                                    <td colspan="9" class="px-4 py-8 text-center text-slate-400">
                                        Tidak ada data token listrik.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
                        <div>
                            Halaman {{ tables.electricity?.current_page ?? 1 }}
                            dari {{ tables.electricity?.last_page ?? 1 }}
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="!tables.electricity?.prev_page_url"
                                @click="changePage('electricity', (tables.electricity?.current_page ?? 1) - 1)"
                                class="rounded-lg border px-3 py-1 disabled:opacity-40"
                            >
                                Prev
                            </button>

                            <button
                                type="button"
                                :disabled="!tables.electricity?.next_page_url"
                                @click="changePage('electricity', (tables.electricity?.current_page ?? 1) + 1)"
                                class="rounded-lg border px-3 py-1 disabled:opacity-40"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                
                <!-- PRINTER -->
                <div class="rounded-3xl border bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">
                                Mesin Cetak
                            </h3>
                            <p class="text-sm text-slate-400">
                                Data hasil scan counter mesin cetak.
                            </p>
                        </div>

                        <span class="rounded-full bg-purple-50 px-3 py-1 text-xs font-bold text-purple-700">
                            {{ formatNumber(tables.printer?.total ?? 0) }} data
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Cabang</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Mesin</th>
                                    <th class="px-4 py-3">Serial</th>
                                    <th class="px-4 py-3">BW A3</th>
                                    <th class="px-4 py-3">BW A4</th>
                                    <th class="px-4 py-3">Color A3</th>
                                    <th class="px-4 py-3">Color A4</th>
                                    <th class="px-4 py-3">Gambar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in tables.printer?.data ?? []"
                                    :key="row.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(row.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-700">
                                            {{ row.user?.name ?? '-' }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ row.user?.phone ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-700">
                                            {{ row.cabang?.nama_cabang ?? '-' }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ row.cabang?.kode_cabang ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-bold ring-1"
                                            :class="getStatusClass(row.status)"
                                        >
                                            {{ row.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ getDataPenting(row).nama_mesin ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ getDataPenting(row).serial_number ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatNumber(getDataPenting(row).bw_a3) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatNumber(getDataPenting(row).bw_a4) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatNumber(getDataPenting(row).color_a3) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatNumber(getDataPenting(row).color_a4) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            v-if="getImageUrl(row.image_path)"
                                            :href="getImageUrl(row.image_path)"
                                            target="_blank"
                                            class="font-bold text-blue-600 hover:underline"
                                        >
                                            Lihat
                                        </a>
                                        <span v-else>-</span>
                                    </td>
                                </tr>

                                <tr v-if="!tables.printer?.data?.length">
                                    <td colspan="11" class="px-4 py-8 text-center text-slate-400">
                                        Tidak ada data mesin cetak.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
                        <div>
                            Halaman {{ tables.printer?.current_page ?? 1 }}
                            dari {{ tables.printer?.last_page ?? 1 }}
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="!tables.printer?.prev_page_url"
                                @click="changePage('printer', (tables.printer?.current_page ?? 1) - 1)"
                                class="rounded-lg border px-3 py-1 disabled:opacity-40"
                            >
                                Prev
                            </button>

                            <button
                                type="button"
                                :disabled="!tables.printer?.next_page_url"
                                @click="changePage('printer', (tables.printer?.current_page ?? 1) + 1)"
                                class="rounded-lg border px-3 py-1 disabled:opacity-40"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ONLINE RECEIPT -->
                <div class="rounded-3xl border bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">
                                Struk Online
                            </h3>
                            <p class="text-sm text-slate-400">
                                Data hasil scan struk online.
                            </p>
                        </div>

                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                            {{ formatNumber(tables.online_receipt?.total ?? 0) }} data
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Cabang</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Gambar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in tables.online_receipt?.data ?? []"
                                    :key="row.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(row.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-700">
                                            {{ row.user?.name ?? '-' }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ row.user?.phone ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-700">
                                            {{ row.cabang?.nama_cabang ?? '-' }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ row.cabang?.kode_cabang ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-bold ring-1"
                                            :class="getStatusClass(row.status)"
                                        >
                                            {{ row.status }}
                                        </span>
                                    </td>                                    
                                    <td class="px-4 py-3 font-bold text-slate-700">
                                        Rp {{ formatNumber(getDataPenting(row).total_pembayaran) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            v-if="getImageUrl(row.image_path)"
                                            :href="getImageUrl(row.image_path)"
                                            target="_blank"
                                            class="font-bold text-blue-600 hover:underline"
                                        >
                                            Lihat
                                        </a>
                                        <span v-else>-</span>
                                    </td>
                                </tr>

                                <tr v-if="!tables.online_receipt?.data?.length">
                                    <td colspan="9" class="px-4 py-8 text-center text-slate-400">
                                        Tidak ada data struk online.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
                        <div>
                            Halaman {{ tables.online_receipt?.current_page ?? 1 }}
                            dari {{ tables.online_receipt?.last_page ?? 1 }}
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="!tables.online_receipt?.prev_page_url"
                                @click="changePage('online_receipt', (tables.online_receipt?.current_page ?? 1) - 1)"
                                class="rounded-lg border px-3 py-1 disabled:opacity-40"
                            >
                                Prev
                            </button>

                            <button
                                type="button"
                                :disabled="!tables.online_receipt?.next_page_url"
                                @click="changePage('online_receipt', (tables.online_receipt?.current_page ?? 1) + 1)"
                                class="rounded-lg border px-3 py-1 disabled:opacity-40"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>