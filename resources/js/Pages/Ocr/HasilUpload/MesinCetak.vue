<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    title: {
        type: String,
        default: 'Hasil Upload',
    },
    scanType: {
        type: String,
        default: 'printer',
    },
});

const dataList = ref([]);
const loading = ref(false);

const getData = async () => {
    loading.value = true;

    try {
        const res = await axios.get('/api/image-scans', {
            params: {
                scan_type: props.scanType,
            },
        });

        dataList.value = res.data.data;
    } catch (error) {
        console.error('Gagal mengambil data:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    getData();
});

const parseResult = (item) => {
    try {
        if (!item.analysis_result) return null;

        if (typeof item.analysis_result === 'object') {
            return item.analysis_result;
        }

        return JSON.parse(item.analysis_result);
    } catch (e) {
        console.log('JSON parse error:', e, item.analysis_result);
        return null;
    }
};

const getImportantData = (item) => {
    const result = parseResult(item);
    return result?.data_penting || {};
};

const extractCounters = (text) => {
    if (!text) return [];

    const results = [];

    const regexDetail = /(\d{3})\s+Total\s+\((.*?)\)\s+(\d+)/g;
    let match;

    while ((match = regexDetail.exec(text)) !== null) {
        results.push({
            code: match[1],
            label: match[2],
            value: parseInt(match[3], 10),
            isTotal: false,
        });
    }

    const regexTotal = /101\s+Total\s+1\s+(\d+)/;
    const totalMatch = text.match(regexTotal);

    if (totalMatch) {
        results.unshift({
            code: '101',
            label: 'Total Keseluruhan',
            value: parseInt(totalMatch[1], 10),
            isTotal: true,
        });
    }

    return results;
};

const getTotal = (list) => {
    return list.find((i) => i.isTotal);
};

const getDetailCounters = (list) => {
    return list.filter((i) => !i.isTotal);
};

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '-';
    return value;
};
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ title }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <div v-if="loading" class="py-10 text-center text-gray-500">
                        Memuat data...
                    </div>

                    <div v-else class="grid gap-4">

                        <div
                            v-for="item in dataList"
                            :key="item.id"
                            class="flex flex-col gap-4 rounded-xl border bg-white p-5 shadow md:flex-row"
                        >
                            <div class="w-32 flex-shrink-0">
                                <img
                                    :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'"
                                    class="h-32 w-32 rounded-lg border object-cover"
                                >
                            </div>

                            <div class="flex-1">

                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                                        {{ item.scan_type }}
                                    </span>

                                    <span
                                        class="rounded px-3 py-1 text-xs font-semibold"
                                        :class="{
                                            'bg-yellow-100 text-yellow-700': item.status === 'pending',
                                            'bg-blue-100 text-blue-700': item.status === 'processing',
                                            'bg-green-100 text-green-700': item.status === 'success',
                                            'bg-red-100 text-red-700': item.status === 'failed',
                                        }"
                                    >
                                        {{ item.status }}
                                    </span>
                                </div>

                                <template v-if="parseResult(item)">
                                    <div class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-3">

                                        <template v-if="props.scanType === 'printer'">
                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Tanggal</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).tanggal) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Nama Mesin</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).nama_mesin) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Lokasi</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).lokasi) }}
                                                </div>
                                            </div>
                                        </template>

                                        <template v-else-if="props.scanType === 'electricity'">
                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Tanggal</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).tanggal) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">No Meter</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).nomor_meter) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">ID Pelanggan</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).id_pelanggan) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Nama Pelanggan</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).nama_pelanggan) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Nominal</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).nominal) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-green-50 p-2 text-sm">
                                                <div class="text-green-600">Token</div>
                                                <div class="font-bold text-green-700">
                                                    {{ formatValue(getImportantData(item).token) }}
                                                </div>
                                            </div>
                                        </template>

                                        <template v-else>
                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Tanggal</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).tanggal) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Nama Toko</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).nama_toko) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">No Pesanan</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).nomor_pesanan) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Total Pembayaran</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).total_pembayaran) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Metode Pembayaran</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).metode_pembayaran) }}
                                                </div>
                                            </div>

                                            <div class="rounded bg-gray-50 p-2 text-sm">
                                                <div class="text-gray-400">Status</div>
                                                <div class="font-semibold">
                                                    {{ formatValue(getImportantData(item).status_pembayaran) }}
                                                </div>
                                            </div>
                                        </template>

                                    </div>

                                    <div
                                        v-if="props.scanType === 'printer' && extractCounters(parseResult(item).teks_terbaca).length"
                                        class="mb-3"
                                    >
                                        <div
                                            v-if="getTotal(extractCounters(parseResult(item).teks_terbaca))"
                                            class="mb-4 rounded-xl bg-green-100 p-4"
                                        >
                                            <div class="text-sm font-medium text-green-700">
                                                Total Keseluruhan
                                            </div>

                                            <div class="mt-1 text-3xl font-bold text-green-800">
                                                {{
                                                    getTotal(extractCounters(parseResult(item).teks_terbaca))
                                                        .value.toLocaleString('id-ID')
                                                }}
                                            </div>
                                        </div>

                                        <h3 class="mb-2 font-semibold text-gray-700">
                                            📊 Data Counter
                                        </h3>

                                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                            <div
                                                v-for="c in getDetailCounters(extractCounters(parseResult(item).teks_terbaca))"
                                                :key="c.code"
                                                class="flex justify-between rounded bg-blue-50 p-3 text-sm"
                                            >
                                                <div>
                                                    <div class="font-semibold text-gray-700">
                                                        {{ c.label }}
                                                    </div>
                                                    <div class="text-xs text-gray-400">
                                                        Kode {{ c.code }}
                                                    </div>
                                                </div>

                                                <span class="font-bold text-blue-700">
                                                    {{ c.value.toLocaleString('id-ID') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 rounded bg-gray-50 p-3 text-sm text-gray-600">
                                        <div class="mb-1 font-semibold text-gray-700">
                                            Ringkasan
                                        </div>
                                        {{ parseResult(item).ringkasan || '-' }}
                                    </div>
                                </template>

                                <template v-else>
                                    <div v-if="item.status === 'pending' || item.status === 'processing'" class="text-blue-500">
                                        Gambar sedang dianalisis...
                                    </div>

                                    <div v-else class="text-red-500">
                                        Data hasil analisis tidak valid
                                    </div>
                                </template>

                            </div>
                        </div>

                        <div v-if="dataList.length === 0" class="py-10 text-center text-gray-500">
                            Belum ada data hasil upload.
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>