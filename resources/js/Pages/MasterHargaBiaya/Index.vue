<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    hargaBiayas: Object,
    cabangs: Array,
    vendors: Array,
    filters: Object,
});

const kategoriOptions = [
    { value: 'biaya_umum', label: 'Biaya Umum' },
    { value: 'token_listrik', label: 'Token Listrik' },
    { value: 'klik_meter', label: 'Klik Meter' },
    { value: 'part', label: 'Part' },
    { value: 'maintenance_mesin', label: 'Maintenance Mesin' },
    { value: 'lembur', label: 'Lembur' },
    { value: 'kendaraan', label: 'Kendaraan' },
    { value: 'sewa_cabang', label: 'Sewa Cabang' },
    { value: 'skpd', label: 'SKPD' },
];

const tipeHargaOptions = [
    { value: 'warna', label: 'Warna' },
    { value: 'hitam_putih', label: 'Hitam Putih' },
    { value: 'minimum', label: 'Minimum' },
    { value: 'normal', label: 'Normal' },
    { value: 'part', label: 'Part' },
    { value: 'lembur', label: 'Lembur' },
];

const search = ref(props.filters.search || '');
const masterCabangId = ref(props.filters.master_cabang_id || '');
const kategoriBiaya = ref(props.filters.kategori_biaya || '');
const isCoa = ref(props.filters.is_coa || '');

const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    master_cabang_id: '',
    master_vendor_id: '',
    kategori_biaya: '',
    nama_biaya: '',
    tipe_harga: '',
    nominal: 0,
    satuan: '',
    is_coa: false,
    kode_coa: '',
    nama_coa: '',
    keterangan: '',
    is_active: true,
});

watch([search, masterCabangId, kategoriBiaya, isCoa], ([searchValue, cabangValue, kategoriValue, coaValue]) => {
    router.get(
        route('master-harga-biaya.index'),
        {
            search: searchValue,
            master_cabang_id: cabangValue,
            kategori_biaya: kategoriValue,
            is_coa: coaValue,
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
    form.master_vendor_id = '';
    form.nominal = 0;
    form.is_coa = false;
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;

    form.clearErrors();

    form.master_cabang_id = item.master_cabang_id || '';
    form.master_vendor_id = item.master_vendor_id || '';
    form.kategori_biaya = item.kategori_biaya || '';
    form.nama_biaya = item.nama_biaya || '';
    form.tipe_harga = item.tipe_harga || '';
    form.nominal = item.nominal || 0;
    form.satuan = item.satuan || '';
    form.is_coa = Boolean(item.is_coa);
    form.kode_coa = item.kode_coa || '';
    form.nama_coa = item.nama_coa || '';
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
    if (!form.is_coa) {
        form.kode_coa = '';
        form.nama_coa = '';
    }

    if (isEdit.value) {
        form.put(route('master-harga-biaya.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-harga-biaya.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const destroyData = (id) => {
    if (confirm('Yakin ingin menghapus data harga/biaya ini?')) {
        router.delete(route('master-harga-biaya.destroy', id), {
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

const getKategoriLabel = (value) => {
    return kategoriOptions.find((item) => item.value === value)?.label || value || '-';
};

const getTipeHargaLabel = (value) => {
    return tipeHargaOptions.find((item) => item.value === value)?.label || value || '-';
};
</script>

<template>
    <Head title="Master Harga / Biaya" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Master Harga / Biaya
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari biaya..."
                            class="rounded-md border-gray-300 shadow-sm"
                        />

                        <select
                            v-model="masterCabangId"
                            class="rounded-md border-gray-300 shadow-sm"
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

                        <select
                            v-model="kategoriBiaya"
                            class="rounded-md border-gray-300 shadow-sm"
                        >
                            <option value="">Semua Kategori</option>
                            <option
                                v-for="item in kategoriOptions"
                                :key="item.value"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>

                        <select
                            v-model="isCoa"
                            class="rounded-md border-gray-300 shadow-sm"
                        >
                            <option value="">Semua COA</option>
                            <option value="1">COA</option>
                            <option value="0">Bukan COA</option>
                        </select>

                        <button
                            type="button"
                            @click="openCreate"
                            class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 md:col-span-1 md:row-start-2"
                        >
                            + Tambah Biaya
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border p-2 text-left">Cabang</th>
                                    <th class="border p-2 text-left">Vendor</th>
                                    <th class="border p-2 text-left">Kategori</th>
                                    <th class="border p-2 text-left">Nama Biaya</th>
                                    <th class="border p-2 text-left">Tipe</th>
                                    <th class="border p-2 text-right">Nominal</th>
                                    <th class="border p-2 text-left">Satuan</th>
                                    <th class="border p-2 text-left">COA</th>
                                    <th class="border p-2 text-left">Status</th>
                                    <th class="border p-2 text-center">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="item in hargaBiayas.data" :key="item.id">
                                    <td class="border p-2">
                                        {{ item.cabang?.nama_cabang || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.vendor?.nama_vendor || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        {{ getKategoriLabel(item.kategori_biaya) }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.nama_biaya }}
                                    </td>
                                    <td class="border p-2">
                                        {{ getTipeHargaLabel(item.tipe_harga) }}
                                    </td>
                                    <td class="border p-2 text-right">
                                        {{ formatRupiah(item.nominal) }}
                                    </td>
                                    <td class="border p-2">
                                        {{ item.satuan || '-' }}
                                    </td>
                                    <td class="border p-2">
                                        <span v-if="item.is_coa" class="text-green-600">
                                            {{ item.kode_coa || 'COA' }}
                                        </span>
                                        <span v-else class="text-gray-500">
                                            Bukan COA
                                        </span>
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

                                <tr v-if="hargaBiayas.data.length === 0">
                                    <td colspan="10" class="border p-4 text-center text-gray-500">
                                        Data harga/biaya belum tersedia.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="hargaBiayas.links" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="link in hargaBiayas.links"
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
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white shadow-lg">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ isEdit ? 'Edit Harga / Biaya' : 'Tambah Harga / Biaya' }}
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Cabang
                            </label>
                            <select
                                v-model="form.master_cabang_id"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option value="">Semua / Tidak Spesifik</option>
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
                                Vendor
                            </label>
                            <select
                                v-model="form.master_vendor_id"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option value="">Tanpa Vendor</option>
                                <option
                                    v-for="vendor in vendors"
                                    :key="vendor.id"
                                    :value="vendor.id"
                                >
                                    {{ vendor.nama_vendor }}
                                </option>
                            </select>
                            <div class="text-sm text-red-600">
                                {{ form.errors.master_vendor_id }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Kategori Biaya
                            </label>
                            <select
                                v-model="form.kategori_biaya"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option value="">Pilih Kategori</option>
                                <option
                                    v-for="item in kategoriOptions"
                                    :key="item.value"
                                    :value="item.value"
                                >
                                    {{ item.label }}
                                </option>
                            </select>
                            <div class="text-sm text-red-600">
                                {{ form.errors.kategori_biaya }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Tipe Harga
                            </label>
                            <select
                                v-model="form.tipe_harga"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option value="">Tidak Ada</option>
                                <option
                                    v-for="item in tipeHargaOptions"
                                    :key="item.value"
                                    :value="item.value"
                                >
                                    {{ item.label }}
                                </option>
                            </select>
                            <div class="text-sm text-red-600">
                                {{ form.errors.tipe_harga }}
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Nama Biaya
                            </label>
                            <input
                                v-model="form.nama_biaya"
                                type="text"
                                placeholder="Contoh: Biaya Listrik Bulanan / Toner Warna / Sewa Cabang"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.nama_biaya }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nominal
                            </label>
                            <input
                                v-model="form.nominal"
                                type="number"
                                min="0"
                                step="100"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.nominal }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Satuan
                            </label>
                            <input
                                v-model="form.satuan"
                                type="text"
                                placeholder="Contoh: bulan, lembar, unit, jam"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" v-model="form.is_coa" />
                                <span>Termasuk COA</span>
                            </label>
                        </div>

                        <template v-if="form.is_coa">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Kode COA
                                </label>
                                <input
                                    v-model="form.kode_coa"
                                    type="text"
                                    class="mt-1 w-full rounded border-gray-300"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Nama COA
                                </label>
                                <input
                                    v-model="form.nama_coa"
                                    type="text"
                                    class="mt-1 w-full rounded border-gray-300"
                                />
                            </div>
                        </template>
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