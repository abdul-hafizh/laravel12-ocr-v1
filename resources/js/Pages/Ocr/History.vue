<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

// Data Dummy Riwayat Aktivitas
const historyLogs = [
    {
        id: 1,
        action: 'Extraction Completed',
        target: 'Invoice_PT_Maju_Jaya.pdf',
        time: '10:45 AM',
        date: 'Today',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        color: 'text-emerald-500',
        bg: 'bg-emerald-50'
    },
    {
        id: 2,
        action: 'Account Login',
        target: 'Device: Chrome on Windows',
        time: '08:20 AM',
        date: 'Today',
        icon: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
        color: 'text-blue-500',
        bg: 'bg-blue-50'
    },
    {
        id: 3,
        action: 'Batch Upload',
        target: '5 files successfully uploaded',
        time: '04:15 PM',
        date: 'Yesterday',
        icon: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
        color: 'text-[#2DD4BF]',
        bg: 'bg-[#2DD4BF]/10'
    },
    {
        id: 4,
        action: 'Export Data',
        target: 'Monthly_Report_April.csv',
        time: '11:00 AM',
        date: 'Yesterday',
        icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
        color: 'text-purple-500',
        bg: 'bg-purple-50'
    },
    {
        id: 5,
        action: 'Extraction Failed',
        target: 'Blurry_Image_001.jpg',
        time: '09:30 AM',
        date: '25 Apr 2026',
        icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        color: 'text-red-500',
        bg: 'bg-red-50'
    }
];
</script>

<template>
    <Head title="Activity History" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                    Activity <span class="text-[#2DD4BF]">History</span>
                </h2>
                <p class="hidden sm:block mt-1 text-sm text-slate-400 font-medium">Lacak semua aktivitas sistem dan audit log Anda.</p>
            </div>
        </template>

        <div class="max-w-4xl">
            <div class="bg-white rounded-[2.5rem] border border-slate-200/60 shadow-sm p-8 md:p-12">
                <div class="relative">
                    <div class="absolute left-[21px] top-2 bottom-2 w-0.5 bg-slate-100"></div>

                    <div class="space-y-12">
                        <div v-for="log in historyLogs" :key="log.id" class="relative flex items-start ml-1">
                            <div :class="[log.bg, log.color]" class="relative z-10 w-10 h-10 rounded-xl flex items-center justify-center shadow-sm border border-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="log.icon" />
                                </svg>
                            </div>

                            <div class="ml-6 flex-1">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-1">
                                    <h4 class="font-bold text-[#1E293B] text-lg">{{ log.action }}</h4>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-300">{{ log.date }}</span>
                                        <span class="w-1 h-1 rounded-full bg-slate-200"></span>
                                        <span class="text-xs font-bold text-slate-400">{{ log.time }}</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mt-1 font-medium italic">{{ log.target }}</p>
                                
                                <button class="mt-3 text-[10px] font-black text-[#2DD4BF] uppercase tracking-[0.2em] hover:text-[#1E293B] transition-colors flex items-center">
                                    Details
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-16 flex justify-center">
                    <button class="px-8 py-3 bg-slate-50 text-slate-500 rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-100 transition-all border border-slate-200/50">
                        Load Older Activities
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>