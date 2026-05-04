<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
    message: Object,
});

const show = ref(false);

const triggerToast = () => {
    if (props.message?.text) {
        show.value = true;
        setTimeout(() => (show.value = false), 5000);
    }
};

onMounted(() => triggerToast());
watch(() => props.message, () => triggerToast());
</script>

<template>
    <Transition
        enter-active-class="transform transition ease-out duration-300"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed top-5 right-5 z-[100] max-w-sm w-full bg-white shadow-2xl rounded-[1.5rem] border border-gray-100 p-4 flex items-center space-x-4">
            <div :class="{
                'bg-[#2DD4BF]': props.message.type === 'success',
                'bg-red-500': props.message.type === 'error',
                'bg-blue-500': props.message.type === 'info'
            }" class="p-2 rounded-xl text-white shadow-lg">
                <svg v-if="props.message.type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="flex-1 text-sm font-bold text-[#1E293B]">
                {{ props.message.text }}
            </div>
        </div>
    </Transition>
</template>