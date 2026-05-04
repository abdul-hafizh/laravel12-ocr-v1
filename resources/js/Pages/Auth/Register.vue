<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Account" />

        <div class="mb-8 text-center">
            <h1 class="text-2xl font-extrabold text-[#1E293B]">Join OCRHub</h1>
            <p class="text-gray-400 text-sm mt-2">Start your document automation journey</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="name" value="Full Name" class="text-gray-500 font-semibold mb-1.5 ml-1" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full bg-[#F1F5F9] border-transparent focus:border-[#2DD4BF] focus:ring-[#2DD4BF]/20 rounded-2xl py-3 px-5 transition-all duration-300"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Enter your full name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email Address" class="text-gray-500 font-semibold mb-1.5 ml-1" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full bg-[#F1F5F9] border-transparent focus:border-[#2DD4BF] focus:ring-[#2DD4BF]/20 rounded-2xl py-3 px-5 transition-all duration-300"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="example@mail.com"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" class="text-gray-500 font-semibold mb-1.5 ml-1" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full bg-[#F1F5F9] border-transparent focus:border-[#2DD4BF] focus:ring-[#2DD4BF]/20 rounded-2xl py-3 px-5 transition-all duration-300"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="Create a password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm Password" class="text-gray-500 font-semibold mb-1.5 ml-1" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full bg-[#F1F5F9] border-transparent focus:border-[#2DD4BF] focus:ring-[#2DD4BF]/20 rounded-2xl py-3 px-5 transition-all duration-300"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Repeat your password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-4 bg-[#2DD4BF] hover:bg-[#14B8A6] active:bg-[#0D9488] rounded-2xl text-sm font-bold shadow-lg shadow-teal-100 transition-all transform active:scale-[0.98]"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Creating account...' : 'Create Account' }}
                </PrimaryButton>
            </div>

            <div class="text-center mt-6">
                <p class="text-sm text-gray-400 font-medium">
                    Already have an account? 
                    <Link :href="route('login')" class="text-[#2DD4BF] font-bold hover:underline">
                        Log in here
                    </Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>