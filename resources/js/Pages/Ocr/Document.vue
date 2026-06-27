<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

// Data Dummy Dokumen
const documents = ref([
    { id: 1, name: 'Invoice-March-2026.pdf', category: 'Finance', date: '28 Apr 2026', size: '1.2 MB', thumbnail: 'INV' },
    { id: 2, name: 'KTP_Ichsan_Nurhakim.jpg', category: 'Identity', date: '25 Apr 2026', size: '850 KB', thumbnail: 'ID' },
    { id: 3, name: 'Business_Contract_Final.pdf', category: 'Legal', date: '20 Apr 2026', size: '4.5 MB', thumbnail: 'DOC' },
    { id: 4, name: 'Grocery_Receipt_Feb.png', category: 'Expense', date: '15 Mar 2026', size: '320 KB', thumbnail: 'RCP' },
    { id: 5, name: 'Medical_Report_A1.pdf', category: 'Health', date: '10 Mar 2026', size: '2.1 MB', thumbnail: 'MED' },
    { id: 6, name: 'University_Certificate.jpg', category: 'Education', date: '01 Mar 2026', size: '1.8 MB', thumbnail: 'EDU' },
]);

const categories = ['All', 'Finance', 'Identity', 'Legal', 'Expense', 'Health'];
const activeCategory = ref('All');
</script>

<template>
    <Head title="My Documents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
                <div>
                    <h2 class="font-bold text-2xl text-[#1E293B] leading-tight">
                        My <span class="text-[#2DD4BF]">Documents</span>
                    </h2>
                    <p class="hidden sm:block mt-1 text-sm text-slate-400 font-medium">Kelola dan cari hasil ekstraksi data Anda.</p>
                </div>
                
                <div class="flex items-center space-x-3">
                    <button class="px-5 py-3 bg-white border border-slate-200 text-slate-600 rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-slate-50 transition-all shadow-sm">
                        Export All
                    </button>
                    <button class="px-6 py-3 bg-[#2DD4BF] text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:shadow-lg hover:shadow-teal-200 transition-all flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Upload New
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-8">
            <div class="flex flex-col lg:flex-row gap-4 justify-between">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"/></svg>
                    </span>
                    <input 
                        type="text" 
                        placeholder="Search by filename or content..." 
                        class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200/60 rounded-[1.5rem] text-sm focus:border-[#2DD4BF] focus:ring-0 transition-all shadow-sm"
                    />
                </div>

                <div class="flex items-center space-x-2 overflow-x-auto pb-2 lg:pb-0">
                    <button 
                        v-for="cat in categories" :key="cat"
                        @click="activeCategory = cat"
                        :class="[activeCategory === cat ? 'bg-[#1E293B] text-white shadow-lg' : 'bg-white text-slate-500 hover:bg-slate-50 border border-slate-200/60']"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    >
                        {{ cat }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <div 
                    v-for="doc in documents" :key="doc.id"
                    class="group bg-white p-5 rounded-[2.5rem] border border-slate-200/60 shadow-sm hover:shadow-xl hover:border-[#2DD4BF]/20 transition-all duration-300 relative overflow-hidden"
                >
                    <div class="aspect-video bg-slate-50 rounded-[2rem] mb-5 flex items-center justify-center relative overflow-hidden group-hover:bg-[#2DD4BF]/5 transition-colors">
                        <span class="text-4xl font-black text-slate-200 group-hover:text-[#2DD4BF]/20 transition-colors uppercase">{{ doc.thumbnail }}</span>
                        
                        <div class="absolute inset-0 bg-[#1E293B]/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-3 backdrop-blur-[2px]">
                            <button class="p-3 bg-white rounded-xl text-[#1E293B] hover:bg-[#2DD4BF] hover:text-white transition-all transform hover:scale-110">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2"/></svg>
                            </button>
                            <button class="p-3 bg-white rounded-xl text-[#1E293B] hover:bg-[#2DD4BF] hover:text-white transition-all transform hover:scale-110">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="px-2 pb-2">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-[#1E293B] truncate pr-4">{{ doc.name }}</h4>
                            <div class="w-2 h-2 rounded-full bg-[#2DD4BF] shadow-[0_0_8px_#2DD4BF]"></div>
                        </div>
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-50">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Category</span>
                                <span class="text-xs font-bold text-[#2DD4BF]">{{ doc.category }}</span>
                            </div>
                            <div class="flex flex-col text-right">
                                <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Created</span>
                                <span class="text-xs font-bold text-slate-500">{{ doc.date }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center pt-8 space-x-2">
                <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-[#2DD4BF] transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-[#2DD4BF] text-white font-bold shadow-lg shadow-teal-100">1</button>
                <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 font-bold hover:bg-slate-50">2</button>
                <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-[#2DD4BF] transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>