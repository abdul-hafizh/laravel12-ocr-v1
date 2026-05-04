<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

// Data Dummy untuk Statistik
const stats = [
    { name: 'Total Scans', value: '1,284', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: 'bg-blue-500' },
    { name: 'Accuracy Rate', value: '98.2%', icon: 'M13 10V3L4 14h7v7l9-11h-7z', color: 'bg-[#2DD4BF]' },
    { name: 'Storage Used', value: '4.2 GB', icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4', color: 'bg-purple-500' },
    { name: 'Active Credits', value: '850', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'bg-amber-500' },
];

// Data Dummy untuk Tabel Aktivitas
const recentActivities = [
    { id: 'OCR-001', name: 'Invoice_PT_Maju.pdf', date: '28 Apr 2026', status: 'Completed', size: '1.2 MB' },
    { id: 'OCR-002', name: 'KTP_Customer_01.jpg', date: '27 Apr 2026', status: 'Completed', size: '850 KB' },
    { id: 'OCR-003', name: 'Kontrak_Kerja_V2.pdf', date: '27 Apr 2026', status: 'Processing', size: '4.5 MB' },
    { id: 'OCR-004', name: 'Struk_Belanja_Feb.png', date: '26 Apr 2026', status: 'Failed', size: '500 KB' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                    Overview <span class="text-[#2DD4BF]">Dashboard</span>
                </h2>
                <p class="text-sm text-slate-400 font-medium mt-1">Selamat datang kembali, pantau performa OCR Anda hari ini.</p>
            </div>
        </template>

        <div class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div v-for="stat in stats" :key="stat.name" class="bg-white p-6 rounded-[2rem] border border-slate-200/60 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ stat.name }}</p>
                            <h3 class="text-2xl font-black text-[#1E293B] mt-1">{{ stat.value }}</h3>
                        </div>
                        <div :class="stat.color" class="w-12 h-12 rounded-2xl flex items-center justify-center text-white shadow-lg opacity-90">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-[10px] font-bold text-emerald-500 uppercase tracking-tighter">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 10l7-7m0 0l7 7m-7-7v18" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        +12.5% dari bulan lalu
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 bg-white rounded-[2.5rem] border border-slate-200/60 shadow-sm overflow-hidden">
                    <div class="p-8 border-b border-slate-50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-[#1E293B]">Recent Scans</h3>
                        <button class="text-xs font-bold text-[#2DD4BF] hover:underline uppercase tracking-widest">View All</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50">
                                <tr>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">File Name</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Size</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="item in recentActivities" :key="item.id" class="hover:bg-slate-50/30 transition-colors">
                                    <td class="px-8 py-5">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </div>
                                            <span class="text-sm font-bold text-[#1E293B]">{{ item.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 text-sm text-slate-500 font-medium">{{ item.date }}</td>
                                    <td class="px-8 py-5">
                                        <span :class="{
                                            'bg-emerald-50 text-emerald-600': item.status === 'Completed',
                                            'bg-amber-50 text-amber-600': item.status === 'Processing',
                                            'bg-red-50 text-red-600': item.status === 'Failed'
                                        }" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter italic">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-sm text-slate-500 font-bold">{{ item.size }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-[#1E293B] rounded-[2.5rem] p-8 text-white relative overflow-hidden shadow-xl">
                        <div class="relative z-10">
                            <h4 class="text-xs font-bold uppercase tracking-[0.2em] opacity-60">System Credits</h4>
                            <div class="mt-4 flex items-end justify-between">
                                <h2 class="text-4xl font-black">850<span class="text-sm font-normal opacity-40 ml-1">/1000</span></h2>
                            </div>
                            <div class="mt-6 w-full bg-white/10 h-2 rounded-full overflow-hidden">
                                <div class="bg-[#2DD4BF] h-full w-[85%] rounded-full shadow-[0_0_15px_rgba(45,212,191,0.5)]"></div>
                            </div>
                            <p class="mt-4 text-[10px] font-medium opacity-50">Sisa kuota Anda mencukupi untuk 150 scan lagi.</p>
                            <button class="mt-8 w-full bg-white text-[#1E293B] py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-[#2DD4BF] hover:text-white transition-all shadow-lg shadow-black/20">Upgrade Plan</button>
                        </div>
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-[#2DD4BF]/10 rounded-full blur-3xl"></div>
                    </div>

                    <div class="bg-white rounded-[2rem] border border-slate-200/60 p-6 shadow-sm">
                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 bg-teal-50 text-[#2DD4BF] rounded-xl flex shrink-0 items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-[#1E293B]">System Update</h5>
                                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Model OCR ditingkatkan ke v2.4. Sekarang lebih cepat dalam memproses dokumen tulisan tangan.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>