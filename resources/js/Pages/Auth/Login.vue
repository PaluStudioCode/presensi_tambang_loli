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
    'mt-2 block w-full rounded-lg border bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-amber-500/20',
    hasError ? 'border-rose-300 dark:border-rose-500/50' : 'border-slate-200 dark:border-slate-700',
];

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login" />

    <main class="relative min-h-screen w-screen overflow-hidden bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <section class="grid min-h-screen w-full items-stretch bg-white dark:bg-slate-900 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="relative hidden min-h-screen overflow-hidden bg-slate-950 lg:block">
                <img
                    src="/storage/bg-login.jpeg"
                    alt="Area operasional PT. ABDARA BRM TAMBANG"
                    class="h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-slate-950/55"></div>

                <div class="absolute inset-x-0 top-0 p-8">
                    <ApplicationLogo large light class="max-w-md drop-shadow" />
                </div>

                <div class="absolute inset-x-0 bottom-0 p-8">
                    <p class="max-w-xl text-3xl font-semibold leading-tight text-white drop-shadow">
                        Sistem presensi kerja yang rapi untuk operasional tambang.
                    </p>
                    <p class="mt-4 max-w-xl text-base leading-7 text-slate-100">
                        Sistem cerdas pencatatan kehadiran dan lembur kerja karyawan PT. ABDARA BRM TAMBANG yang cepat, presisi, dan transparan.
                    </p>
                </div>
            </div>

            <div class="flex min-h-screen flex-col bg-white dark:bg-slate-900">
                <div class="relative h-44 shrink-0 overflow-hidden lg:hidden">
                    <img
                        src="/storage/bg-login.jpeg"
                        alt="Area operasional PT. ABDARA BRM TAMBANG"
                        class="h-full w-full object-cover"
                    />
                    <div class="absolute inset-0 bg-slate-950/55"></div>
                    <div class="absolute inset-x-0 bottom-0 p-4">
                        <ApplicationLogo light class="drop-shadow" />
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-center px-6 py-8 sm:px-10 lg:px-16">
                    <div class="w-full max-w-md">
                        <div class="hidden lg:block">
                            <p class="text-sm font-medium uppercase tracking-[0.18em] text-amber-600 dark:text-amber-300">Portal Karyawan</p>
                            <h1 class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">Masuk ke Presensi</h1>
                        </div>

                        <div class="lg:hidden">
                            <p class="text-sm font-medium uppercase tracking-[0.18em] text-amber-600 dark:text-amber-300">Portal Karyawan</p>
                            <h1 class="mt-3 text-2xl font-bold text-slate-950 dark:text-white">Masuk ke Presensi</h1>
                        </div>

                        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Gunakan email atau NIK dan password yang sudah terdaftar.
                        </p>

                        <div v-if="status" class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                            {{ status }}
                        </div>

                        <form class="mt-8 space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="id_number" class="text-sm font-medium text-slate-700 dark:text-slate-200">Email / NIK</label>
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
                                    <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-sm font-medium text-amber-700 transition hover:text-amber-600 dark:text-amber-300 dark:hover:text-amber-200"
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
                                <span class="ms-2 text-sm text-slate-600 dark:text-slate-300">Ingat saya</span>
                            </label>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
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
            </div>
        </section>
    </main>
</template>
