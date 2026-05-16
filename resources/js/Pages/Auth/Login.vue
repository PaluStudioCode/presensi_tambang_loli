<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    id_number: '',
    password: '',
    remember: false,
});

const inputClass = (hasError) => [
    'mt-2 block w-full rounded-lg border bg-white px-4 py-3 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100 dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-500 dark:focus:ring-blue-500/20',
    hasError ? 'border-red-300 dark:border-red-500/50' : 'border-blue-100 dark:border-blue-200',
];

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login" />

    <main class="relative min-h-screen overflow-x-hidden bg-blue-950 text-slate-900 dark:bg-blue-950 dark:text-slate-900">
        <div class="absolute inset-0">
            <img
                src="/storage/bg-login.jpeg"
                alt="Area operasional PT. ABDARA BRM TAMBANG"
                class="h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-blue-950/70 dark:bg-blue-950/70"></div>
        </div>

        <section class="relative z-10 grid min-h-screen place-items-center px-4 py-8 sm:px-6 lg:px-8">
            <div class="w-full max-w-[29rem]">
                <div class="mb-6 flex justify-center">
                    <ApplicationLogo light class="max-w-full justify-center" />
                </div>

                <div class="rounded-lg border border-blue-100 bg-white/95 px-5 py-6 backdrop-blur-md dark:border-blue-100 dark:bg-white/95 sm:px-8 sm:py-8">
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700 dark:text-blue-700">Portal Karyawan</p>
                        <h1 class="mt-3 text-2xl font-bold text-blue-950 dark:text-blue-950 sm:text-3xl">Masuk ke Presensi</h1>
                        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-600">
                            Gunakan email atau NIK dan password yang sudah terdaftar.
                        </p>
                    </div>

                    <div v-if="status" class="mt-5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 dark:border-blue-200 dark:bg-blue-50 dark:text-blue-700">
                        {{ status }}
                    </div>

                    <form class="mt-7 space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="id_number" class="text-sm font-medium text-slate-700 dark:text-slate-700">Email / NIK</label>
                            <input
                                id="id_number"
                                v-model="form.id_number"
                                type="text"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Masukkan email atau NIK"
                                :class="inputClass(!!form.errors.id_number)"
                            />
                            <InputError class="mt-2" :message="form.errors.id_number" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-700">Password</label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-sm font-medium text-blue-700 transition hover:text-blue-800 dark:text-blue-700 dark:hover:text-blue-800"
                                >
                                    Lupa password?
                                </Link>
                            </div>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Masukkan password"
                                :class="inputClass(!!form.errors.password)"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <label class="flex items-center">
                            <Checkbox name="remember" v-model:checked="form.remember" />
                            <span class="ms-2 text-sm text-slate-600 dark:text-slate-600">Ingat saya</span>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-70 dark:bg-blue-700 dark:text-white dark:hover:bg-blue-800"
                            :class="{ 'cursor-wait': form.processing }"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                        >
                            <span
                                v-if="form.processing"
                                class="me-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
                                aria-hidden="true"
                            />
                            {{ form.processing ? 'Memproses...' : 'Login' }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</template>
