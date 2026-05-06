<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Toast from '@/Components/Toast.vue';

const page = usePage();
const flashMessage = computed(() => page.props.flash.message);

const isSidebarOpen = ref(true);

const isMasterDataOpen = ref(
    route().current('master-cabang.*') ||
    route().current('master-vendor.*') ||
    route().current('master-mesin.*') ||
    route().current('master-token-listrik.*') ||
    route().current('master-kendaraan.*') ||
    route().current('master-skpd.*') ||
    route().current('master-harga-biaya.*')
);

const isHasilUploadOpen = ref(
    route().current('hasil-upload.*')
);

watch(() => route().current(), () => {
    if (route().current('hasil-upload.*')) {
        isHasilUploadOpen.value = true;
    }
});

const masterDataMenus = [
    {
        name: 'Master Cabang',
        href: route('master-cabang.index'),
        current: route().current('master-cabang.*'),
    },
    {
        name: 'Master Vendor',
        href: route('master-vendor.index'),
        current: route().current('master-vendor.*'),
    },
    {
        name: 'Master Mesin',
        href: route('master-mesin.index'),
        current: route().current('master-mesin.*'),
    },
    {
        name: 'Master Token Listrik',
        href: route('master-token-listrik.index'),
        current: route().current('master-token-listrik.*'),
    },
    {
        name: 'Master Kendaraan',
        href: route('master-kendaraan.index'),
        current: route().current('master-kendaraan.*'),
    },
    {
        name: 'Master SKPD',
        href: route('master-skpd.index'),
        current: route().current('master-skpd.*'),
    },
    {
        name: 'Master Harga Biaya',
        href: route('master-harga-biaya.index'),
        current: route().current('master-harga-biaya.*'),
    },
];

const navigation = [
    {
        name: 'Dashboard',
        href: route('dashboard'),
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        current: route().current('dashboard'),
    },
    {
        name: 'Documents',
        href: route('document'),
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        current: route().current('documents.index'),
    },
    {
        name: 'History',
        href: route('history'),
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        current: route().current('history'),
    },
    {
        name: 'Settings',
        href: route('profile.edit'),
        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
        current: route().current('profile.edit'),
    },
];

const hasilUploadMenus = [
    {
        name: 'Mesin Cetak',
        href: route('hasil-upload.mesin-cetak'),
        current: route().current('hasil-upload.mesin-cetak'),
    },
    {
        name: 'Token Listrik',
        href: route('hasil-upload.token-listrik'),
        current: route().current('hasil-upload.token-listrik'),
    },
    {
        name: 'Struk Online',
        href: route('hasil-upload.struk-online'),
        current: route().current('hasil-upload.struk-online'),
    }
];

</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] flex">
        <Toast :message="flashMessage" />

        <aside
            class="fixed inset-y-0 left-0 bg-white border-r border-slate-200/60 transition-all duration-300 z-50 overflow-hidden"
            :class="[isSidebarOpen ? 'w-72' : 'w-20']"
        >
            <div class="flex flex-col h-full px-4 py-6">
                <div class="flex items-center space-x-3 px-2 mb-10">
                    <div
                        class="w-10 h-10 bg-[#2DD4BF] rounded-xl flex shrink-0 items-center justify-center shadow-lg shadow-teal-100/50"
                    >
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </div>

                    <span
                        v-if="isSidebarOpen"
                        class="text-xl font-bold text-[#1E293B] tracking-tight"
                    >
                        OCR<span class="text-[#2DD4BF]">Hub</span>
                    </span>
                </div>

                <nav class="flex-1 space-y-1.5 overflow-y-auto pr-1">
                    <Link
                        v-for="item in navigation.slice(0, 1)"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            item.current
                                ? 'bg-[#2DD4BF]/10 text-[#2DD4BF] border border-[#2DD4BF]/20'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-[#1E293B] border border-transparent'
                        ]"
                        class="group flex items-center px-4 py-3.5 text-sm font-bold rounded-[1.25rem] transition-all duration-200"
                    >
                        <svg
                            class="w-6 h-6 shrink-0 transition-colors"
                            :class="[item.current ? 'text-[#2DD4BF]' : 'text-slate-300 group-hover:text-slate-500']"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                :d="item.icon"
                            />
                        </svg>

                        <span v-if="isSidebarOpen" class="ms-4">
                            {{ item.name }}
                        </span>
                    </Link>

                    <!-- Master Data Menu -->
                    <button
                        type="button"
                        @click="
                            isMasterDataOpen = !isMasterDataOpen;
                            if (isMasterDataOpen) {
                                isHasilUploadOpen = false;
                            }
                        "
                        :class="[
                            isMasterDataOpen
                                ? 'bg-[#2DD4BF]/10 text-[#2DD4BF] border border-[#2DD4BF]/20'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-[#1E293B] border border-transparent'
                        ]"
                        class="group flex w-full items-center px-4 py-3.5 text-sm font-bold rounded-[1.25rem] transition-all duration-200"
                    >
                        <svg
                            class="w-6 h-6 shrink-0 transition-colors"
                            :class="[isMasterDataOpen ? 'text-[#2DD4BF]' : 'text-slate-300 group-hover:text-slate-500']"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16"
                            />
                        </svg>

                        <span v-if="isSidebarOpen" class="ms-4 flex-1 text-left">
                            Master Data
                        </span>

                        <svg
                            v-if="isSidebarOpen"
                            class="h-4 w-4 transition-transform"
                            :class="{ 'rotate-180': isMasterDataOpen }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <div
                        v-if="isSidebarOpen && isMasterDataOpen"
                        class="ml-6 space-y-1 border-l border-slate-100 pl-3"
                    >
                        <Link
                            v-for="item in masterDataMenus"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                item.current
                                    ? 'bg-[#2DD4BF]/10 text-[#2DD4BF]'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-[#1E293B]'
                            ]"
                            class="block rounded-xl px-4 py-2.5 text-sm font-semibold transition-all"
                        >
                            {{ item.name }}
                        </Link>
                    </div>

                    <!-- Hasil Upload Menu -->
                    <button
                        type="button"
                        @click="
                            isHasilUploadOpen = !isHasilUploadOpen;
                            if (isHasilUploadOpen) {
                                isMasterDataOpen = false;
                            }
                        "
                        :class="[
                            isHasilUploadOpen
                                ? 'bg-[#2DD4BF]/10 text-[#2DD4BF] border border-[#2DD4BF]/20'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-[#1E293B] border border-transparent'
                        ]"
                        class="group flex w-full items-center px-4 py-3.5 text-sm font-bold rounded-[1.25rem] transition-all duration-200"
                    >
                        <svg
                            class="w-6 h-6 shrink-0 transition-colors"
                            :class="[isHasilUploadOpen ? 'text-[#2DD4BF]' : 'text-slate-300 group-hover:text-slate-500']"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M12 12v9m0-9l-3 3m3-3l3 3"
                            />
                        </svg>

                        <span v-if="isSidebarOpen" class="ms-4 flex-1 text-left">
                            Hasil Upload
                        </span>

                        <svg
                            v-if="isSidebarOpen"
                            class="h-4 w-4 transition-transform"
                            :class="{ 'rotate-180': isHasilUploadOpen }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <div
                        v-if="isSidebarOpen && isHasilUploadOpen"
                        class="ml-6 space-y-1 border-l border-slate-100 pl-3"
                    >
                        <Link
                            v-for="item in hasilUploadMenus"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                item.current
                                    ? 'bg-[#2DD4BF]/10 text-[#2DD4BF]'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-[#1E293B]'
                            ]"
                            class="block rounded-xl px-4 py-2.5 text-sm font-semibold transition-all"
                        >
                            {{ item.name }}
                        </Link>
                    </div>                    

                    <Link
                        v-for="item in navigation.slice(1)"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            item.current
                                ? 'bg-[#2DD4BF]/10 text-[#2DD4BF] border border-[#2DD4BF]/20'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-[#1E293B] border border-transparent'
                        ]"
                        class="group flex items-center px-4 py-3.5 text-sm font-bold rounded-[1.25rem] transition-all duration-200"
                    >
                        <svg
                            class="w-6 h-6 shrink-0 transition-colors"
                            :class="[item.current ? 'text-[#2DD4BF]' : 'text-slate-300 group-hover:text-slate-500']"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                :d="item.icon"
                            />
                        </svg>

                        <span v-if="isSidebarOpen" class="ms-4">
                            {{ item.name }}
                        </span>
                    </Link>
                </nav>

                <button
                    @click="isSidebarOpen = !isSidebarOpen"
                    class="mt-auto flex items-center justify-center w-full py-3 bg-slate-50 border border-slate-100 text-slate-400 hover:text-[#1E293B] rounded-2xl transition-all"
                >
                    <svg
                        class="w-5 h-5 transition-transform"
                        :class="{ 'rotate-180': !isSidebarOpen }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M11 19l-7-7 7-7m8 14l-7-7 7-7"
                        />
                    </svg>
                </button>
            </div>
        </aside>

        <div
            class="flex-1 flex flex-col transition-all duration-300"
            :class="[isSidebarOpen ? 'ms-72' : 'ms-20']"
        >
            <header
                class="h-20 bg-white/90 backdrop-blur-md border-b border-slate-200/60 sticky top-0 z-40 px-8 flex items-center justify-between"
            >
                <div>
                    <slot name="header" />
                </div>

                <div class="flex items-center space-x-4">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                class="flex items-center space-x-3 bg-white border border-slate-200/80 p-1.5 pe-4 rounded-2xl hover:border-[#2DD4BF]/30 transition-all shadow-sm"
                            >
                                <div
                                    class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center text-[#2DD4BF] font-extrabold"
                                >
                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                </div>

                                <div class="text-left hidden md:block">
                                    <p class="text-[13px] font-bold text-[#1E293B] leading-none">
                                        {{ $page.props.auth.user.name }}
                                    </p>
                                    <p class="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-tighter italic">
                                        Pro User
                                    </p>
                                </div>
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink :href="route('profile.edit')">
                                Profile
                            </DropdownLink>

                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="text-red-500 font-bold"
                            >
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="p-8">
                <slot />
            </main>
        </div>
    </div>
</template>