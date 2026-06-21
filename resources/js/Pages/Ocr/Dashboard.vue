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

const showImageModal = ref(false);
const selectedImageUrl = ref('');

const openImagePreview = (url) => {
    selectedImageUrl.value = url;
    showImageModal.value = true;
};

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
    </AuthenticatedLayout>
</template>