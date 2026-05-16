<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    full_name: user.full_name,
    department: user.department ?? '',
    address: user.address ?? '',
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Informasi Profil</h2>

            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Perbarui informasi profil, departemen, alamat, dan email akun Anda.
            </p>
        </header>

        <form @submit.prevent="form.patch(route('profile.update'))" class="mt-6 space-y-6">
            <div class="grid gap-x-5 gap-y-6 lg:grid-cols-2">
                <div>
                    <InputLabel for="full_name" value="Nama Lengkap" />

                    <TextInput
                        id="full_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.full_name"
                        required
                        autofocus
                        autocomplete="name"
                    />

                    <InputError class="mt-2" :message="form.errors.full_name" />
                </div>

                <div>
                    <InputLabel for="department" value="Departemen" />

                    <TextInput
                        id="department"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.department"
                        autocomplete="organization"
                    />

                    <InputError class="mt-2" :message="form.errors.department" />
                </div>

                <div>
                    <InputLabel for="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autocomplete="username"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="lg:col-span-2">
                    <InputLabel for="address" value="Alamat" />

                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="3"
                        class="mt-1 block w-full rounded-lg border border-blue-100 bg-white px-4 py-3 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100 dark:border-blue-200 dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-500 dark:focus:ring-blue-500/10"
                        autocomplete="street-address"
                    ></textarea>

                    <InputError class="mt-2" :message="form.errors.address" />
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                    Alamat email Anda belum diverifikasi.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-lg font-medium underline underline-offset-4 transition hover:text-amber-950 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:hover:text-amber-100 dark:focus:ring-amber-500/10"
                    >
                        Klik di sini untuk mengirim ulang email verifikasi.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                    Tautan verifikasi baru sudah dikirim ke alamat email Anda.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-300">Tersimpan.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
