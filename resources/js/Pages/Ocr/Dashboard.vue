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

        options.value = res.data.filters;
        summary.value = res.data.summary;
        tables.value = res.data.data;
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
                                :key="cabang"
                                :value="cabang"
                            >
                                {{ cabang }}
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
                        @click="applyFilter"
                        class="rounded-xl bg-slate-800 px-5 py-2 text-sm font-bold text-white hover:bg-slate-700"
                    >
                        Filter
                    </button>

                    <button
                        @click="resetFilter"
                        class="rounded-xl bg-slate-100 px-5 py-2 text-sm font-bold text-slate-600 hover:bg-slate-200"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- SUMMARY -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-3xl bg-white p-5 shadow-sm border">
                    <div class="text-xs font-bold uppercase text-slate-400">Total Upload</div>
                    <div class="mt-2 text-3xl font-black text-slate-800">{{ summary.total }}</div>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm border">
                    <div class="text-xs font-bold uppercase text-slate-400">Token Listrik</div>
                    <div class="mt-2 text-3xl font-black text-green-600">{{ summary.electricity }}</div>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm border">
                    <div class="text-xs font-bold uppercase text-slate-400">Struk Online</div>
                    <div class="mt-2 text-3xl font-black text-blue-600">{{ summary.online_receipt }}</div>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm border">
                    <div class="text-xs font-bold uppercase text-slate-400">Mesin Cetak</div>
                    <div class="mt-2 text-3xl font-black text-purple-600">{{ summary.printer }}</div>
                </div>
            </div>

            <div v-if="loading" class="rounded-3xl bg-white p-10 text-center text-slate-500 shadow-sm">
                Memuat data dashboard...
            </div>

            <template v-else>
                <!-- TOKEN LISTRIK -->
                <div class="rounded-3xl border bg-white shadow-sm overflow-hidden">
                    <div class="border-b p-5">
                        <h3 class="text-lg font-bold text-slate-800">Token Listrik</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3">Tanggal Upload</th>
                                    <th class="px-5 py-3">User Phone</th>
                                    <th class="px-5 py-3">Cabang</th>
                                    <th class="px-5 py-3">kWh</th>
                                    <th class="px-5 py-3">Barcode</th>
                                    <th class="px-5 py-3">Nomor Meter</th>
                                    <th class="px-5 py-3">Lokasi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="item in tables.electricity?.data || []"
                                    :key="item.id"
                                    class="border-t"
                                >
                                    <td class="px-5 py-3">{{ formatDate(item.created_at) }}</td>
                                    <td class="px-5 py-3">{{ item.user_phone || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.nama_cabang || '-' }}</td>
                                    <td class="px-5 py-3 font-bold text-green-700">{{ item.kwh || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.barcode || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.nomor_meter || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.lokasi || '-' }}</td>
                                </tr>

                                <tr v-if="(tables.electricity?.data || []).length === 0">
                                    <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                        Tidak ada data token listrik.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        :data="tables.electricity"
                        @change="changePage('electricity', $event)"
                    />
                </div>

                <!-- STRUK ONLINE -->
                <div class="rounded-3xl border bg-white shadow-sm overflow-hidden">
                    <div class="border-b p-5">
                        <h3 class="text-lg font-bold text-slate-800">Struk Online</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3">Tanggal Upload</th>
                                    <th class="px-5 py-3">User Phone</th>
                                    <th class="px-5 py-3">Cabang</th>
                                    <th class="px-5 py-3">Nama Toko</th>
                                    <th class="px-5 py-3">No Pesanan</th>
                                    <th class="px-5 py-3">Total Bayar</th>
                                    <th class="px-5 py-3">Metode</th>
                                    <th class="px-5 py-3">Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="item in tables.online_receipt?.data || []"
                                    :key="item.id"
                                    class="border-t"
                                >
                                    <td class="px-5 py-3">{{ formatDate(item.created_at) }}</td>
                                    <td class="px-5 py-3">{{ item.user_phone || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.nama_cabang || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.nama_toko || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.nomor_pesanan || '-' }}</td>
                                    <td class="px-5 py-3 font-bold text-blue-700">
                                        Rp {{ formatNumber(item.total_pembayaran) }}
                                    </td>
                                    <td class="px-5 py-3">{{ item.metode_pembayaran || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.status_pembayaran || '-' }}</td>
                                </tr>

                                <tr v-if="(tables.online_receipt?.data || []).length === 0">
                                    <td colspan="8" class="px-5 py-8 text-center text-slate-400">
                                        Tidak ada data struk online.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        :data="tables.online_receipt"
                        @change="changePage('online_receipt', $event)"
                    />
                </div>

                <!-- MESIN CETAK -->
                <div class="rounded-3xl border bg-white shadow-sm overflow-hidden">
                    <div class="border-b p-5">
                        <h3 class="text-lg font-bold text-slate-800">Mesin Cetak</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3">Tanggal Upload</th>
                                    <th class="px-5 py-3">User Phone</th>
                                    <th class="px-5 py-3">Cabang</th>
                                    <th class="px-5 py-3">Tanggal Data</th>
                                    <th class="px-5 py-3">Nama Mesin</th>
                                    <th class="px-5 py-3">Lokasi</th>
                                    <th class="px-5 py-3">BW Large</th>
                                    <th class="px-5 py-3">BW Small</th>
                                    <th class="px-5 py-3">Color Large</th>
                                    <th class="px-5 py-3">Color Small</th>
                                    <th class="px-5 py-3">Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="item in tables.printer?.data || []"
                                    :key="item.id"
                                    class="border-t"
                                >
                                    <td class="px-5 py-3">{{ formatDate(item.created_at) }}</td>
                                    <td class="px-5 py-3">{{ item.user_phone || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.nama_cabang || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.tanggal || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.nama_mesin || '-' }}</td>
                                    <td class="px-5 py-3">{{ item.lokasi || '-' }}</td>
                                    <td class="px-5 py-3">{{ formatNumber(item.total_black_white_large) }}</td>
                                    <td class="px-5 py-3">{{ formatNumber(item.total_black_white_small) }}</td>
                                    <td class="px-5 py-3">{{ formatNumber(item.total_full_color_large) }}</td>
                                    <td class="px-5 py-3">{{ formatNumber(item.total_full_color_small) }}</td>
                                    <td class="px-5 py-3 font-bold text-purple-700">
                                        {{ formatNumber(item.total_counter) }}
                                    </td>
                                </tr>

                                <tr v-if="(tables.printer?.data || []).length === 0">
                                    <td colspan="11" class="px-5 py-8 text-center text-slate-400">
                                        Tidak ada data mesin cetak.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        :data="tables.printer"
                        @change="changePage('printer', $event)"
                    />
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

<script>
export default {
    components: {
        Pagination: {
            props: {
                data: {
                    type: Object,
                    default: null,
                },
            },
            emits: ['change'],
            template: `
                <div v-if="data" class="flex items-center justify-between border-t px-5 py-4">
                    <div class="text-sm text-slate-500">
                        Halaman {{ data.current_page }} dari {{ data.last_page }}
                        | Total {{ data.total }} data
                    </div>

                    <div class="flex gap-2">
                        <button
                            :disabled="data.current_page <= 1"
                            @click="$emit('change', data.current_page - 1)"
                            class="rounded-lg border px-3 py-1 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Prev
                        </button>

                        <button
                            :disabled="data.current_page >= data.last_page"
                            @click="$emit('change', data.current_page + 1)"
                            class="rounded-lg border px-3 py-1 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Next
                        </button>
                    </div>
                </div>
            `,
        },
    },
};
</script>