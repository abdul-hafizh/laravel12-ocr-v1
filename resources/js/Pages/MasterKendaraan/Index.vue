<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    kendaraans: Object,
    cabangs: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const masterCabangId = ref(props.filters.master_cabang_id || '');

const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    master_cabang_id: '',
    jenis_kendaraan: '',
    nomor_polisi: '',
    merk: '',
    tipe: '',
    tahun_pembelian: '',
    nama_pemilik: '',
    tanggal_jatuh_tempo: '',
    reminder_hari: 14,
    keterangan: '',
    is_active: true,
});

watch([search, masterCabangId], ([searchValue, cabangValue]) => {
    router.get(
        route('master-kendaraan.index'),
        {
            search: searchValue,
            master_cabang_id: cabangValue,
        },
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

    form.master_cabang_id = '';
    form.reminder_hari = 14;
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;

    form.clearErrors();

    form.master_cabang_id = item.master_cabang_id || '';
    form.jenis_kendaraan = item.jenis_kendaraan || '';
    form.nomor_polisi = item.nomor_polisi || '';
    form.merk = item.merk || '';
    form.tipe = item.tipe || '';
    form.tahun_pembelian = item.tahun_pembelian || '';
    form.nama_pemilik = item.nama_pemilik || '';
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

const submit = () => {
    if (isEdit.value) {
        form.put(route('master-kendaraan.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-kendaraan.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const destroyData = (id) => {
    if (confirm('Yakin ingin menghapus data kendaraan ini?')) {
        router.delete(route('master-kendaraan.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Master Kendaraan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Master Kendaraan
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="flex flex-col gap-3 md:flex-row">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Cari kendaraan..."
                                class="w-full rounded-md border-gray-300 shadow-sm md:w-80"
                            />

                            <select
                                v-model="masterCabangId"
                                class="w-full rounded-md border-gray-300 shadow-sm md:w-64"
                            >
                                <option value="">Semua Cabang</option>
                                <option
                                    v-for="cabang in cabangs"
                                    :key="cabang.id"
                                    :value="cabang.id"
                                >
                                    {{ cabang.nama_cabang }}
                                </option>
                            </select>
                        </div>

                        <button
                            type="button"
                            @click="openCreate"
                            class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                        >
                            + Tambah Kendaraan
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border p-2 text-left">Cabang</th>
                                    <th class="border p-2 text-left">Jenis</th>
                                    <th class="border p-2 text-left">No Polisi</th>
                                    <th class="border p-2 text-left">Merk</th>
                                    <th class="border p-2 text-left">Tipe</th>
                                    <th class="border p-2 text-left">Tahun</th>
                                    <th class="border p-2 text-left">Pemilik</th>
                                    <th class="border p-2 text-left">Jatuh Tempo</th>
                                    <th class="border p-2 text-left">Reminder</th>
                                    <th class="border p-2 text-left">Status</th>
                                    <th class="border p-2 text-center">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="item in kendaraans.data" :key="item.id">
                                    <td class="border p-2">
                                        {{ item.cabang?.nama_cabang || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.jenis_kendaraan }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.nomor_polisi }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.merk || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.tipe || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.tahun_pembelian || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.nama_pemilik || '-' }}
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

                                <tr v-if="kendaraans.data.length === 0">
                                    <td colspan="11" class="border p-4 text-center text-gray-500">
                                        Data kendaraan belum tersedia.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="kendaraans.links" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="link in kendaraans.links"
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
                        {{ isEdit ? 'Edit Kendaraan' : 'Tambah Kendaraan' }}
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
                                Cabang
                            </label>
                            <select
                                v-model="form.master_cabang_id"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option value="">Pilih Cabang</option>
                                <option
                                    v-for="cabang in cabangs"
                                    :key="cabang.id"
                                    :value="cabang.id"
                                >
                                    {{ cabang.nama_cabang }}
                                </option>
                            </select>
                            <div class="text-sm text-red-600">
                                {{ form.errors.master_cabang_id }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Jenis Kendaraan
                            </label>
                            <select
                                v-model="form.jenis_kendaraan"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option value="">Pilih Jenis</option>
                                <option value="motor">Motor</option>
                                <option value="mobil">Mobil</option>
                            </select>
                            <div class="text-sm text-red-600">
                                {{ form.errors.jenis_kendaraan }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nomor Polisi
                            </label>
                            <input
                                v-model="form.nomor_polisi"
                                type="text"
                                placeholder="Contoh: B 1234 ABC"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.nomor_polisi }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Merk
                            </label>
                            <input
                                v-model="form.merk"
                                type="text"
                                placeholder="Contoh: Honda / Toyota"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Tipe
                            </label>
                            <input
                                v-model="form.tipe"
                                type="text"
                                placeholder="Contoh: Beat / Avanza"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Tahun Pembelian
                            </label>
                            <input
                                v-model="form.tahun_pembelian"
                                type="number"
                                min="1900"
                                max="2100"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.tahun_pembelian }}
                            </div>
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