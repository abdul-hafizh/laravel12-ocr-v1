<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total_cabang: 0,
            total_mesin: 0,
            total_user: 0,
        }),
    },
});

const formatNumber = (value) => {
    return Number(value || 0).toLocaleString("id-ID");
};

const cards = [
    {
        title: "Total Cabang",
        value: props.summary.total_cabang,
        description: "Cabang aktif yang terdaftar di sistem",
        icon: "🏢",
    },
    {
        title: "Total Mesin",
        value: props.summary.total_mesin,
        description: "Mesin aktif yang sudah dimapping",
        icon: "🖨️",
    },
    {
        title: "Total User",
        value: props.summary.total_user,
        description: "User aktif yang dapat mengakses sistem",
        icon: "👤",
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="min-w-0">
                <h2
                    class="text-lg sm:text-xl lg:text-2xl font-bold text-slate-800 truncate"
                >
                    Dashboard
                </h2>

                <p
                    class="mt-1 text-xs sm:text-sm text-slate-400 hidden sm:block"
                >
                    Selamat datang di sistem monitoring mesin dan hasil upload.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <div
                class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm"
            >
                <h3 class="text-2xl font-black text-slate-800">
                    Selamat Datang 👋
                </h3>

                <p class="mt-2 text-sm text-slate-500 max-w-2xl">
                    Gunakan dashboard ini untuk melihat ringkasan data utama
                    seperti cabang, mesin, dan user aktif yang terdaftar pada
                    sistem.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div
                    v-for="card in cards"
                    :key="card.title"
                    class="bg-white rounded-[2rem] border border-slate-200 p-6 shadow-sm hover:shadow-md transition"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-[11px] font-black text-slate-400 uppercase tracking-widest"
                            >
                                {{ card.title }}
                            </p>

                            <h4 class="mt-4 text-4xl font-black text-[#1E293B]">
                                {{ formatNumber(card.value) }}
                            </h4>
                        </div>

                        <div
                            class="w-12 h-12 rounded-2xl bg-[#2DD4BF]/10 flex items-center justify-center text-2xl"
                        >
                            {{ card.icon }}
                        </div>
                    </div>

                    <p class="mt-5 text-sm text-slate-400 font-medium">
                        {{ card.description }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
