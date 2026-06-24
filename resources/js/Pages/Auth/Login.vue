<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

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
                    <div class="flex justify-between items-center mb-1.5 ml-1">
                        <InputLabel for="password" value="Password" class="text-gray-500 font-semibold" />
                        <Link v-if="canResetPassword" :href="route('password.request')"
                            class="text-xs font-bold text-[#2DD4BF] hover:text-[#14B8A6]">
                            Forgot?
                        </Link>
                    </div>
                    <TextInput id="password" type="password"
                        class="mt-1 block w-full bg-[#F1F5F9] border-transparent focus:border-[#2DD4BF] focus:ring-[#2DD4BF]/20 rounded-2xl py-3 px-5 transition-all duration-300"
                        v-model="form.password" required placeholder="••••••••" />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
            </div>

            <div class="mt-6 flex items-center ml-1">
                <label class="flex items-center cursor-pointer group">
                    <Checkbox name="remember" v-model:checked="form.remember"
                        class="rounded-lg border-gray-300 text-[#2DD4BF] focus:ring-[#2DD4BF]" />
                    <span class="ms-3 text-sm text-gray-500 group-hover:text-gray-700 transition">Keep me logged
                        in</span>
                </label>
            </div>

            <div class="mt-8">
                <PrimaryButton
                    class="w-full justify-center py-4 bg-[#2DD4BF] hover:bg-[#14B8A6] active:bg-[#0D9488] rounded-2xl text-sm font-bold shadow-lg shadow-teal-100 transition-all transform active:scale-[0.98]"
                    :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    {{ form.processing ? 'Signing in...' : 'Sign In' }}
                </PrimaryButton>
            </div>

            <div class="mt-8 text-center">
                <p class="text-sm text-gray-400 font-medium">
                    Don't have an account?
                    <Link :href="route('register')" class="text-[#2DD4BF] font-bold hover:underline">
                        Create Account
                    </Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>