<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    vendors: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    kode_vendor: '',
    nama_vendor: '',
    pic: '',
    no_hp: '',
    email: '',
    alamat: '',
    keterangan: '',
    is_active: true,
});

watch(search, (value) => {
    router.get(
        route('master-vendor.index'),
        { search: value },
        { preserveState: true, replace: true }
    );
});

const openCreate = () => {
    isEdit.value = false;
    selectedId.value = null;

    form.reset();
    form.clearErrors();
    form.is_active = true;

    showModal.value = true;
};

const openEdit = (item) => {
    isEdit.value = true;
    selectedId.value = item.id;

    form.clearErrors();

    form.kode_vendor = item.kode_vendor;
    form.nama_vendor = item.nama_vendor;
    form.pic = item.pic || '';
    form.no_hp = item.no_hp || '';
    form.email = item.email || '';
    form.alamat = item.alamat || '';
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
        form.put(route('master-vendor.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-vendor.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const destroyData = (id) => {
    if (confirm('Yakin ingin menghapus vendor ini?')) {
        router.delete(route('master-vendor.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Master Vendor" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Master Vendor
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <div class="mb-4 flex items-center justify-between gap-4">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari vendor..."
                            class="w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />

                        <button
                            type="button"
                            @click="openCreate"
                            class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                        >
                            + Tambah Vendor
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border p-2 text-left">Kode</th>
                                    <th class="border p-2 text-left">Nama Vendor</th>
                                    <th class="border p-2 text-left">PIC</th>
                                    <th class="border p-2 text-left">No HP</th>
                                    <th class="border p-2 text-left">Email</th>
                                    <th class="border p-2 text-left">Status</th>
                                    <th class="border p-2 text-center">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="item in vendors.data" :key="item.id">
                                    <td class="border p-2">{{ item.kode_vendor }}</td>
                                    <td class="border p-2">{{ item.nama_vendor }}</td>
                                    <td class="border p-2">{{ item.pic || '-' }}</td>
                                    <td class="border p-2">{{ item.no_hp || '-' }}</td>
                                    <td class="border p-2">{{ item.email || '-' }}</td>
                                    <td class="border p-2">
                                        <span v-if="item.is_active" class="text-green-600">Aktif</span>
                                        <span v-else class="text-red-600">Nonaktif</span>
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

                                <tr v-if="vendors.data.length === 0">
                                    <td colspan="7" class="border p-4 text-center text-gray-500">
                                        Data vendor belum tersedia.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
        >
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-lg">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ isEdit ? 'Edit Vendor' : 'Tambah Vendor' }}
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
                                Kode Vendor
                            </label>
                            <input
                                v-model="form.kode_vendor"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.kode_vendor }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nama Vendor
                            </label>
                            <input
                                v-model="form.nama_vendor"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.nama_vendor }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                PIC
                            </label>
                            <input
                                v-model="form.pic"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                No HP
                            </label>
                            <input
                                v-model="form.no_hp"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Email
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.email }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Alamat
                        </label>
                        <textarea
                            v-model="form.alamat"
                            rows="2"
                            class="mt-1 w-full rounded border-gray-300"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Keterangan
                        </label>
                        <textarea
                            v-model="form.keterangan"
                            rows="2"
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