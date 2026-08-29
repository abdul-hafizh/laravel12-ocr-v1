<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>

        <Head title="Log in" />

        <div class="mb-10 text-center">
            <h1 class="text-2xl font-extrabold text-[#1E293B]">Welcome Back!</h1>
            <p class="text-gray-400 text-sm mt-2">Login to manage your OCR documents</p>
        </div>

        <form @submit.prevent="submit">
            <div class="space-y-5">
                <div>
                    <InputLabel for="email" value="Email or Employee ID"
                        class="text-gray-500 font-semibold mb-1.5 ml-1" />
                    <TextInput id="email" type="text"
                        class="mt-1 block w-full bg-[#F1F5F9] border-transparent focus:border-[#2DD4BF] focus:ring-[#2DD4BF]/20 rounded-2xl py-3 px-5 transition-all duration-300"
                        v-model="form.email" required autofocus placeholder="email or employee id" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Password" class="text-gray-500 font-semibold mb-1.5 ml-1" />
                    <div class="relative">
                        <TextInput id="password" :type="showPassword ? 'text' : 'password'"
                            class="mt-1 block w-full bg-[#F1F5F9] border-transparent focus:border-[#2DD4BF] focus:ring-[#2DD4BF]/20 rounded-2xl py-3 px-5 pr-12 transition-all duration-300"
                            v-model="form.password" required placeholder="••••••••" />

                        <button type="button" @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-[#2DD4BF] transition"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'" tabindex="-1">
                            <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.774 3.162 10.066 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
            </div>

            <div class="mt-8">
                <PrimaryButton
                    class="w-full justify-center py-4 bg-[#2DD4BF] hover:bg-[#14B8A6] active:bg-[#0D9488] rounded-2xl text-sm font-bold shadow-lg shadow-teal-100 transition-all transform active:scale-[0.98]"
                    :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    {{ form.processing ? 'Signing in...' : 'Sign In' }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>