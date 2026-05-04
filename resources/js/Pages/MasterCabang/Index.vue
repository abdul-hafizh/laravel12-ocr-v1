<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    cabangs: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const showModal = ref(false);
const isEdit = ref(false);
const selectedId = ref(null);

const form = useForm({
    kode_cabang: '',
    nama_cabang: '',
    alamat: '',
    pic: '',
    no_hp: '',
    keterangan: '',
    is_active: true,
});

watch(search, (value) => {
    router.get(
        route('master-cabang.index'),
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

    form.kode_cabang = item.kode_cabang;
    form.nama_cabang = item.nama_cabang;
    form.alamat = item.alamat || '';
    form.pic = item.pic || '';
    form.no_hp = item.no_hp || '';
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
        form.put(route('master-cabang.update', selectedId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('master-cabang.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const destroyData = (id) => {
    if (confirm('Yakin ingin menghapus data cabang ini?')) {
        router.delete(route('master-cabang.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Master Cabang" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Master Cabang
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">

                    <div class="mb-4 flex items-center justify-between gap-4">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari cabang..."
                            class="w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />

                        <button
                            type="button"
                            @click="openCreate"
                            class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                        >
                            + Tambah Cabang
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border p-2 text-left">Kode</th>
                                    <th class="border p-2 text-left">Nama Cabang</th>
                                    <th class="border p-2 text-left">PIC</th>
                                    <th class="border p-2 text-left">No HP</th>
                                    <th class="border p-2 text-left">Status</th>
                                    <th class="border p-2 text-center">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="item in cabangs.data" :key="item.id">
                                    <td class="border p-2">{{ item.kode_cabang }}</td>
                                    <td class="border p-2">{{ item.nama_cabang }}</td>
                                    <td class="border p-2">{{ item.pic || '-' }}</td>
                                    <td class="border p-2">{{ item.no_hp || '-' }}</td>
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

                                <tr v-if="cabangs.data.length === 0">
                                    <td colspan="6" class="border p-4 text-center text-gray-500">
                                        Data cabang belum tersedia.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4"
        >
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-lg">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ isEdit ? 'Edit Cabang' : 'Tambah Cabang' }}
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
                                Kode Cabang
                            </label>
                            <input
                                v-model="form.kode_cabang"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.kode_cabang }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nama Cabang
                            </label>
                            <input
                                v-model="form.nama_cabang"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <div class="text-sm text-red-600">
                                {{ form.errors.nama_cabang }}
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