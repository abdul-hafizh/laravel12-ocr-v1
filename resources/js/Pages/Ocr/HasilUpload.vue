<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const dataList = ref([]);

onMounted(async () => {
    const res = await axios.get('/api/image-scans');
    dataList.value = res.data.data;
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

const extractCounters = (text) => {
    if (!text) return [];

    const results = [];

    // Format: 112 Total (Black & White/Large) 00027624
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

</script>

<template>
    <Head title="Hasil Upload" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Hasil Upload
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <!-- LIST DATA -->
                    <div class="grid gap-4">

                        <div v-for="item in dataList" :key="item.id"
                            class="bg-white shadow rounded-xl p-5 border flex flex-col md:flex-row gap-4">

                            <div class="w-32 flex-shrink-0">
                                <img 
                                    :src="item.image_path ? '/storage/' + item.image_path : '/no-image.png'" 
                                    class="w-32 h-32 object-cover rounded-lg border"
                                >
                            </div>

                            <div class="flex-1">

                                <template v-if="parseResult(item)">
                                    
                                    <!-- INFO PENTING -->
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mb-3">
                                        <div class="bg-gray-50 p-2 rounded text-sm">
                                            <div class="text-gray-400">Tanggal</div>
                                            <div class="font-semibold">
                                                {{ parseResult(item).data_penting.tanggal || '-' }}
                                            </div>
                                        </div>

                                        <div class="bg-gray-50 p-2 rounded text-sm">
                                            <div class="text-gray-400">Nama</div>
                                            <div class="font-semibold">
                                                {{ parseResult(item).data_penting.nama || '-' }}
                                            </div>
                                        </div>

                                        <div class="bg-gray-50 p-2 rounded text-sm">
                                            <div class="text-gray-400">No Ref</div>
                                            <div class="font-semibold">
                                                {{ parseResult(item).data_penting.nomor_referensi || '-' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="extractCounters(parseResult(item).teks_terbaca).length" class="mb-3">
                                        <!-- TOTAL UTAMA -->
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

                                        <!-- DATA COUNTER DETAIL -->
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

                                </template>

                                <template v-else>
                                    <div class="text-red-500">
                                        Data hasil analisis tidak valid
                                    </div>
                                </template>

                            </div>
                        </div>

                        <!-- EMPTY STATE -->
                        <div v-if="dataList.length === 0" class="text-center text-gray-500 py-10">
                            Belum ada data hasil upload.
                        </div>

                    </div>

                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>