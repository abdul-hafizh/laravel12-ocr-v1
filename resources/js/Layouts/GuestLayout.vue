<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Toast from '@/Components/Toast.vue';

const page = usePage();

const flashMessage = computed(() => {
    return page.props.flash?.message
        || page.props.flash?.success
        || page.props.flash?.error
        || null;
});
</script>

<template>
    <Toast :message="flashMessage" />

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#F8FAFC] selection:bg-[#2DD4BF]/20">
        <div class="mb-8 flex items-center space-x-2 transition-transform duration-300 hover:scale-105">
            <Link href="/" class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-[#2DD4BF] rounded-xl flex items-center justify-center shadow-lg shadow-teal-100">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.5"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>

                <span class="text-xl font-bold text-[#1E293B] tracking-tight">
                    OCR<span class="text-[#2DD4BF]">Hub</span>
                </span>
            </Link>
        </div>

        <div class="w-full sm:max-w-md mt-2 px-10 py-12 bg-white shadow-[0_20px_60px_-15px_rgba(0,0,0,0.03)] border border-gray-100 sm:rounded-[2.5rem] relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[#2DD4BF]/5 rounded-full -mr-16 -mt-16 blur-3xl"></div>

            <slot />
        </div>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-400 font-medium tracking-wide">
                © 2026 DashOCR Pro System
            </p>
        </div>
    </div>
</template>