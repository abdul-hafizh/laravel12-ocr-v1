<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    skpds: Object,
    kendaraans: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');

const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    master_kendaraan_id: '',
    nomor_skpd: '',
    nama_pemilik: '',
    nomor_polisi: '',
    nominal_pajak: 0,
    tanggal_jatuh_tempo: '',
    reminder_hari: 14,
    keterangan: '',
    is_active: true,
});

watch(search, (value) => {
    router.get(
        route('master-skpd.index'),
        { search: value },
        {
            preserveState: true,
            replace: true,
        }
    );
});

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;

    form.reset();
    form.clearErrors();

    form.master_kendaraan_id = '';
    form.nominal_pajak = 0;
    form.reminder_hari = 14;
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;

    form.clearErrors();

    form.master_kendaraan_id = item.master_kendaraan_id || '';
    form.nomor_skpd = item.nomor_skpd || '';
    form.nama_pemilik = item.nama_pemilik || '';
    form.nomor_polisi = item.nomor_polisi || '';
    form.nominal_pajak = item.nominal_pajak || 0;
    form.tanggal_jatuh_tempo = item.tanggal_jatuh_tempo || '';
    form.reminder_hari = item.reminder_hari || 14;
    form.keterangan = item.keterangan || '';
    form.is_active = Boolean(item.is_active);

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

const onKendaraanChange = () => {
    const kendaraan = props.kendaraans.find(
        (item) => String(item.id) === String(form.master_kendaraan_id)
    );

    if (kendaraan) {
        form.nomor_polisi = kendaraan.nomor_polisi || '';
        form.nama_pemilik = kendaraan.nama_pemilik || '';
    }
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('master-skpd.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-skpd.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const destroyData = (id) => {
    if (confirm('Yakin ingin menghapus data SKPD ini?')) {
        router.delete(route('master-skpd.destroy', id), {
            preserveScroll: true,
        });
    }
};

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0);
};
</script>

<template>
    <Head title="Master SKPD" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Master SKPD
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari nomor SKPD / pemilik / no polisi..."
                            class="w-full rounded-md border-gray-300 shadow-sm md:w-96"
                        />

                        <button
                            type="button"
                            @click="openCreate"
                            class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                        >
                            + Tambah SKPD
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border p-2 text-left">Nomor SKPD</th>
                                    <th class="border p-2 text-left">No Polisi</th>
                                    <th class="border p-2 text-left">Pemilik</th>
                                    <th class="border p-2 text-right">Nominal Pajak</th>
                                    <th class="border p-2 text-left">Jatuh Tempo</th>
                                    <th class="border p-2 text-left">Reminder</th>
                                    <th class="border p-2 text-left">Status</th>
                                    <th class="border p-2 text-center">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="item in skpds.data" :key="item.id">
                                    <td class="border p-2">
                                        {{ item.nomor_skpd }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.nomor_polisi || item.kendaraan?.nomor_polisi || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.nama_pemilik || item.kendaraan?.nama_pemilik || '-' }}
                                    </td>
                                    <td class="border p-2 text-right">
                                        {{ formatRupiah(item.nominal_pajak) }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.tanggal_jatuh_tempo || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        H-{{ item.reminder_hari || 14 }}
                                    </td>
                                    <td class="border p-2">
                                        <span v-if="item.is_active" class="text-green-600">
                                            Aktif
                                        </span>
                                        <span v-else class="text-red-600">
                                            Nonaktif
                                        </span>
                                    </td>
                                    <td class="border p-2 text-center">
                                        <button
                                            @click="openEdit(item)"
                                            class="mr-2 text-blue-600"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            @click="destroyData(item.id)"
                                            class="text-red-600"
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="skpds.data.length === 0">
                                    <td colspan="8" class="border p-4 text-center text-gray-500">
                                        Data SKPD belum tersedia.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="skpds.links" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="link in skpds.links"
                            :key="link.label"
                            v-html="link.label"
                            :disabled="!link.url"
                            @click="link.url && router.visit(link.url, { preserveScroll: true })"
                            class="rounded border px-3 py-1"
                            :class="{
                                'bg-indigo-600 text-white': link.active,
                                'text-gray-400': !link.url,
                            }"
                        />
                    </div>

                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
        >
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-lg">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ isEdit ? 'Edit SKPD' : 'Tambah SKPD' }}
                    </h3>

                    <button
                        type="button"
                        @click="closeModal"
                        class="text-2xl text-gray-500 hover:text-gray-700"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4 p-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Kendaraan
                            </label>
                            <select
                                v-model="form.master_kendaraan_id"
                                @change="onKendaraanChange"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option value="">Pilih Kendaraan</option>
                                <option
                                    v-for="kendaraan in kendaraans"
                                    :key="kendaraan.id"
                                    :value="kendaraan.id"
                                >
                                    {{ kendaraan.nomor_polisi }} - {{ kendaraan.merk || '-' }} {{ kendaraan.tipe || '' }}
                                </option>
                            </select>
                            <div class="text-sm text-red-600">
                                {{ form.errors.master_kendaraan_id }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nomor SKPD
                            </label>
                            <input
                                v-model="form.nomor_skpd"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.nomor_skpd }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                No Polisi
                            </label>
                            <input
                                v-model="form.nomor_polisi"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nama Pemilik
                            </label>
                            <input
                                v-model="form.nama_pemilik"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nominal Pajak
                            </label>
                            <input
                                v-model="form.nominal_pajak"
                                type="number"
                                min="0"
                                step="100"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.nominal_pajak }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Tanggal Jatuh Tempo
                            </label>
                            <input
                                v-model="form.tanggal_jatuh_tempo"
                                type="date"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Reminder Hari
                            </label>
                            <select
                                v-model="form.reminder_hari"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option :value="7">H-7</option>
                                <option :value="14">H-14</option>
                                <option :value="30">H-30</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Keterangan
                        </label>
                        <textarea
                            v-model="form.keterangan"
                            rows="3"
                            class="mt-1 w-full rounded border-gray-300"
                        ></textarea>
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.is_active" />
                        <span>Aktif</span>
                    </label>

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"
                        >
                            Batal
                        </button>

                        <button
                            type="submit"
                            class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>