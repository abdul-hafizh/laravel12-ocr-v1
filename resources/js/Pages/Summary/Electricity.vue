<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    cabangs: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            month: "",
            search: "",
            cabang_id: "",
        }),
    },
});

const getDefaultMonth = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
};

const search = ref(props.filters.search || "");
const month = ref(props.filters.month || getDefaultMonth());
const cabangId = ref(props.filters.cabang_id || "");

// Periode aktual (1 bulan kalender + toleransi telat upload) yang dihitung
// server dari `month` -- dipakai untuk label tampilan/print, bukan untuk
// filter (filter cukup kirim `month`, backend yang menentukan tanggalnya).
const monthLabelFormatter = new Intl.DateTimeFormat("id-ID", {
    month: "long",
    year: "numeric",
});

const periodeLabel = () => {
    if (!props.filters.start_date || !props.filters.end_date) return "-";

    return `${props.filters.start_date} s/d ${props.filters.end_date}`;
};

const monthDisplayLabel = () => {
    if (!month.value) return "-";

    const [y, m] = month.value.split("-").map(Number);
    if (!y || !m) return "-";

    return monthLabelFormatter.format(new Date(y, m - 1, 1));
};

const formatRupiah = (value) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(value || 0);
};

const goToPage = (url) => {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatNumber = (value) => {
    return new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const applyFilter = () => {
    router.get(
        route("summary.electricity"),
        {
            search: search.value,
            month: month.value,
            cabang_id: cabangId.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const printSummary = () => {
    const printWindow = window.open("", "_blank");

    const rows = summaryHtml();

    printWindow.document.write(`
        <html>
        <head>
            <title>Summary Token Listrik</title>
            <style>
                body{
                    font-family: Arial, sans-serif;
                    padding:20px;
                }

                h2{
                    text-align:center;
                    margin-bottom:20px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                }

                th, td{
                    border:1px solid #ddd;
                    padding:8px;
                    font-size:12px;
                    text-align:left;
                }

                th{
                    background:#f3f4f6;
                }

                .text-right{
                    text-align:right;
                }
            </style>
        </head>
        <body>
            <h2>Summary Token Listrik</h2>

            <p>
                Periode : ${monthDisplayLabel()} (${periodeLabel()})
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Cabang</th>
                        <th>Pelanggan</th>
                        <th>kWh Awal</th>
                        <th>Isi Ulang</th>
                        <th>kWh Akhir</th>
                        <th>Pemakaian</th>
                        <th>Estimasi Rupiah</th>
                        <th>Topup Bulan Depan</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
        </body>
        </html>
    `);

    printWindow.document.close();

    setTimeout(() => {
        printWindow.print();
    }, 500);
};

const summaryHtml = () => {
    return (props.summary?.data ?? [])
        .map(
            (item) => `
        <tr>
            <td>
                ${item.nama_cabang || "-"}<br>
                <small>${item.kode_cabang || "-"}</small>
            </td>

            <td>
                ${item.nama_pelanggan || "-"}<br>
                <small>Daya: ${item.daya || "-"}</small>
            </td>

            <td>${formatNumber(item.kwh_awal)}</td>
            <td>${item.total_topup_kwh > 0 ? "+" + formatNumber(item.total_topup_kwh) : "-"}</td>
            <td>${formatNumber(item.kwh_akhir)}</td>
            <td>${formatNumber(item.pemakaian_kwh)}</td>
            <td>${formatRupiah(item.estimasi_pemakaian_rupiah)}</td>
            <td>${formatRupiah(item.rekomendasi_topup_bulan_depan)}</td>
            <td>
                ${
                    item.notes && item.notes.length
                        ? item.notes
                              .map(
                                  (note) => `
                            - ${note.user_name || "-"}: ${note.note || "-"}
                        `,
                              )
                              .join("<br>")
                        : "-"
                }
            </td>
        </tr>
    `,
        )
        .join("");
};

const saveNote = (item) => {
    if (!item.new_note || !item.new_note.trim()) {
        alert("Catatan tidak boleh kosong");
        return;
    }

    router.post(
        route("summary.scan-notes.store"),
        {
            image_scan_id: item.image_scan_id_akhir,
            cabang_id: item.cabang_id,
            note: item.new_note,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const deleteNote = (noteId) => {
    if (!confirm("Hapus catatan ini?")) {
        return;
    }

    router.delete(route("summary.scan-notes.delete", noteId), {
        preserveScroll: true,
        preserveState: true,
    });
};

const resetFilter = () => {
    search.value = "";
    cabangId.value = "";
    month.value = getDefaultMonth();

    router.get(route("summary.electricity"), {
        month: month.value,
    });
};

const badgeClass = (status) => {
    if (!status) return "bg-slate-50 text-slate-500 border-slate-100";

    if (status === "Lengkap") {
        return "bg-emerald-50 text-emerald-600 border-emerald-100";
    }

    // Status kini bisa berupa "Perlu dicek: ..." -> gunakan startsWith
    if (status.startsWith("Perlu dicek")) {
        return "bg-amber-50 text-amber-600 border-amber-100";
    }

    if (status === "Belum lengkap") {
        return "bg-slate-50 text-slate-500 border-slate-100";
    }

    return "bg-rose-50 text-rose-600 border-rose-100";
};
</script>

<template>
    <Head title="Summary Token Listrik" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full"
            >
                <div>
                    <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                        Summary
                        <span class="text-[#2DD4BF]">Token Listrik</span>
                    </h2>
                    <p class="hidden sm:block mt-1 text-sm text-slate-400 font-medium">
                        Perbandingan kWh awal dan akhir bulan untuk rekomendasi
                        top-up bulan berikutnya.
                    </p>
                </div>
            </div>
        </template>
        <div class="mb-3 flex justify-end">
            <div class="flex items-center space-x-3">
                <button
                    type="button"
                    @click="printSummary"
                    class="px-5 py-3 bg-slate-700 text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-800 transition-all shadow-sm"
                >
                    Print
                </button>
            </div>
        </div>

        <div class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="relative md:col-span-1">
                    <span
                        class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                stroke-width="2"
                                stroke-linecap="round"
                            />
                        </svg>
                    </span>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari cabang, barcode, pelanggan, meter, atau phone..."
                        class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                        @keyup.enter="applyFilter"
                    />
                </div>

                <div>
                    <input
                        v-model="month"
                        type="month"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    />
                </div>

                <div>
                    <select
                        v-model="cabangId"
                        class="w-full px-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    >
                        <option value="">Semua Cabang</option>
                        <option
                            v-for="cabang in cabangs"
                            :key="cabang.cabang_id"
                            :value="cabang.cabang_id"
                        >
                            {{ cabang.kode_cabang }} - {{ cabang.nama_cabang }}
                        </option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="applyFilter"
                        class="flex-1 px-6 py-3 bg-[#2DD4BF] text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:shadow-lg hover:shadow-teal-200 transition-all"
                    >
                        Filter
                    </button>

                    <button
                        type="button"
                        @click="resetFilter"
                        class="px-5 py-3 bg-white border border-slate-200 text-slate-500 rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-50 transition-all"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <div
                class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    No
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Foto
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Cabang
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Pelanggan
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Informasi kWh
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Estimasi Biaya
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Bulan Depan
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest"
                                >
                                    Catatan
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(item, index) in summary.data ?? []"
                                :key="item.cabang_id"
                                class="hover:bg-slate-50/70 transition-all"
                            >
                                <td class="px-6 py-5 text-sm font-bold text-slate-500">
                                    {{ (summary.from ?? 1) + index }}
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex gap-3">
                                        <div v-if="item.foto_awal">
                                            <a
                                                :href="`/storage/${item.foto_awal}`"
                                                target="_blank"
                                            >
                                                <img
                                                    :src="`/storage/${item.foto_awal}`"
                                                    class="w-20 h-20 rounded-xl object-cover border"
                                                />
                                            </a>
                                            <p
                                                class="text-[10px] text-slate-400 mt-1 text-center"
                                            >
                                                Awal
                                            </p>
                                        </div>

                                        <div>
                                            <template v-if="item.foto_akhir">
                                                <a
                                                    :href="`/storage/${item.foto_akhir}`"
                                                    target="_blank"
                                                >
                                                    <img
                                                        :src="`/storage/${item.foto_akhir}`"
                                                        class="w-20 h-20 rounded-xl object-cover border"
                                                    />
                                                </a>
                                                <p
                                                    class="text-[10px] text-slate-400 mt-1 text-center"
                                                >
                                                    Akhir
                                                </p>
                                            </template>

                                            <template v-else>
                                                <div
                                                    class="w-20 h-20 rounded-xl border border-dashed border-slate-200 bg-slate-50 flex items-center justify-center text-center px-2"
                                                >
                                                    <span
                                                        class="text-[10px] text-slate-400 leading-tight"
                                                    >
                                                        Belum ada foto akhir
                                                    </span>
                                                </div>
                                                <p
                                                    class="text-[10px] text-slate-400 mt-1 text-center"
                                                >
                                                    Akhir
                                                </p>
                                            </template>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-5">
                                    <div>
                                        <p class="font-bold text-[#1E293B]">
                                            {{ item.nama_cabang }}
                                        </p>
                                        <p class="text-xs text-slate-400 mt-1">
                                            {{ item.kode_cabang }} · Meter:
                                            {{ item.nomor_meter || "-" }}
                                        </p>
                                        <p class="text-xs text-slate-400 mt-1">
                                            Jumlah Foto: {{ item.jumlah_foto }}
                                        </p>
                                    </div>
                                </td>

                                <td class="px-6 py-5">
                                    <p class="font-bold text-[#1E293B]">
                                        {{ item.nama_pelanggan || "-" }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Daya: {{ item.daya || "-" }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ item.status_master_token || "-" }}
                                    </p>
                                </td>

                                <td class="px-6 py-5">
                                    <div class="space-y-1">
                                        <p
                                            class="text-sm font-semibold text-slate-700"
                                        >
                                            Awal:
                                            <span class="font-black">
                                                {{
                                                    formatNumber(item.kwh_awal)
                                                }}
                                            </span>
                                        </p>

                                        <p
                                            v-if="item.total_topup_kwh > 0"
                                            class="text-sm font-semibold text-teal-600"
                                        >
                                            Isi ulang:
                                            <span class="font-black">
                                                +{{
                                                    formatNumber(
                                                        item.total_topup_kwh,
                                                    )
                                                }}
                                            </span>
                                        </p>

                                        <p
                                            class="text-sm font-semibold text-slate-700"
                                        >
                                            Akhir:
                                            <span class="font-black">
                                                {{
                                                    formatNumber(item.kwh_akhir)
                                                }}
                                            </span>
                                        </p>

                                        <p
                                            class="text-sm font-bold text-[#1E293B]"
                                        >
                                            Pemakaian:
                                            <span class="font-black">
                                                {{
                                                    formatNumber(
                                                        item.pemakaian_kwh,
                                                    )
                                                }}
                                            </span>
                                        </p>

                                        <p
                                            v-if="
                                                item.ada_kemungkinan_topup &&
                                                item.total_topup_kwh <= 0
                                            "
                                            class="text-[10px] text-amber-600 mt-1 leading-tight"
                                        >
                                            ⚠ Nomor token berubah tapi isi ulang
                                            tidak tertangkap di foto — pemakaian
                                            bisa lebih rendah dari sebenarnya.
                                        </p>
                                    </div>
                                </td>

                                <td class="px-6 py-5">
                                    <p class="font-bold text-slate-700">
                                        Stroom:
                                        {{
                                            formatRupiah(
                                                item.estimasi_biaya_stroom,
                                            )
                                        }}
                                    </p>

                                    <p class="text-xs text-slate-400 mt-1">
                                        Harga/kWh:
                                        {{ formatRupiah(item.harga_per_kwh) }}
                                    </p>

                                    <p class="text-xs text-slate-400 mt-1">
                                        PPN
                                        {{ formatNumber(item.ppn_persen) }}%:
                                        {{ formatRupiah(item.estimasi_ppn) }}
                                    </p>

                                    <p
                                        class="text-xs font-black text-[#1E293B] mt-1"
                                    >
                                        Total + PPN:
                                        {{
                                            formatRupiah(
                                                item.estimasi_total_dengan_ppn,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-6 py-5">
                                    <p class="font-black text-[#2DD4BF]">
                                        {{
                                            formatRupiah(
                                                item.rekomendasi_topup_bulan_depan,
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Sisa estimasi:
                                        {{
                                            formatRupiah(
                                                item.estimasi_sisa_rupiah,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-6 py-5 min-w-[240px]">
                                    <div class="space-y-3">
                                        <span
                                            class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border"
                                            :class="
                                                badgeClass(item.status_summary)
                                            "
                                        >
                                            {{ item.status_summary || "-" }}
                                        </span>

                                        <div
                                            v-if="
                                                item.notes && item.notes.length
                                            "
                                            class="space-y-1 max-h-24 overflow-y-auto"
                                        >
                                            <div
                                                v-for="note in item.notes"
                                                :key="note.id"
                                                class="bg-slate-50 border border-slate-100 rounded-lg px-2 py-2 text-left"
                                            >
                                                <div
                                                    class="flex items-start justify-between gap-2"
                                                >
                                                    <div>
                                                        <div
                                                            class="text-[9px] font-bold text-slate-600"
                                                        >
                                                            {{
                                                                note.user_name ||
                                                                "-"
                                                            }}
                                                        </div>

                                                        <div
                                                            class="text-[10px] text-slate-500"
                                                        >
                                                            {{ note.note }}
                                                        </div>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        @click="
                                                            deleteNote(note.id)
                                                        "
                                                        class="text-red-500 hover:text-red-700 text-[10px] font-black"
                                                        title="Hapus"
                                                    >
                                                        ✕
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex gap-2">
                                            <input
                                                v-model="item.new_note"
                                                type="text"
                                                placeholder="Tambah catatan..."
                                                class="flex-1 text-[10px] border border-slate-200 rounded-lg px-3 py-2 focus:border-[#2DD4BF] focus:ring-0"
                                                @keyup.enter="saveNote(item)"
                                            />

                                            <button
                                                type="button"
                                                @click="saveNote(item)"
                                                class="px-3 py-2 bg-[#2DD4BF] text-white rounded-lg text-[9px] font-black uppercase"
                                            >
                                                Simpan
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="(summary.data ?? []).length === 0">
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <p class="text-slate-400 font-semibold">
                                        Belum ada data summary token listrik
                                        pada periode ini.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="(summary.data ?? []).length > 0"
                        class="flex flex-col md:flex-row items-center justify-between gap-4 px-6 py-5 border-t border-slate-100 bg-slate-50/50"
                    >
                        <div class="text-xs font-bold text-slate-500">
                            Menampilkan
                            {{ summary.from || 0 }}
                            -
                            {{ summary.to || 0 }}
                            dari
                            {{ summary.total || 0 }}
                            data
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-for="(link, index) in summary.links"
                                :key="index"
                                type="button"
                                @click="goToPage(link.url)"
                                :disabled="!link.url"
                                class="min-w-9 px-3 py-2 rounded-xl border text-[10px] font-black disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="
                                    link.active
                                        ? 'bg-[#2DD4BF] border-[#2DD4BF] text-white'
                                        : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100'
                                "
                            >
                                {{
                                    link.label.includes("Previous")
                                        ? "‹"
                                        : link.label.includes("Next")
                                          ? "›"
                                          : link.label
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>