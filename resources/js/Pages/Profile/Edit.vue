<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import Modal from '@/Components/Modal.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfilePhotoForm from './Partials/UpdateProfilePhotoForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const activeModal = ref(null);
const isEmployee = computed(() => user.value?.role === 'Employee');
const roleLabel = computed(() => (user.value?.role === 'Employee' ? 'Karyawan' : user.value?.role ?? '-'));
const displayName = computed(() => user.value?.full_name ?? 'User');
const userInitials = computed(() => displayName.value
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || 'U');

const openModal = (name) => {
    activeModal.value = name;
};

const closeModal = () => {
    activeModal.value = null;
};

const formatDate = (value) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Makassar',
    }).format(new Date(value));
};

const profileItems = computed(() => [
    { label: 'Nama Lengkap', value: user.value?.full_name ?? '-' },
    { label: 'Nomor ID', value: user.value?.id_number ?? '-' },
    { label: 'Departemen', value: user.value?.department ?? '-' },
    { label: 'Alamat', value: user.value?.address ?? '-' },
    { label: 'Email', value: user.value?.email ?? '-' },
    { label: 'Peran', value: roleLabel.value },
    { label: 'Akun Dibuat', value: formatDate(user.value?.created_at) },
]);
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(300px,0.8fr)_minmax(0,1.2fr)] lg:items-start">
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6 lg:p-8">
                    <div class="flex flex-col gap-6">
                        <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-center lg:flex-col lg:items-start xl:flex-row xl:items-center">
                            <div class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-lg border border-blue-100 bg-blue-50 text-2xl font-semibold text-blue-700 dark:border-blue-100 dark:bg-blue-50 dark:text-blue-700">
                                <img
                                    v-if="user?.profile_photo_url"
                                    :src="user.profile_photo_url"
                                    alt="Foto profil"
                                    class="h-full w-full object-cover"
                                >
                                <span v-else>{{ userInitials }}</span>
                            </div>

                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700 dark:text-blue-700">{{ roleLabel }}</p>
                                <h3 class="mt-2 break-words text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ displayName }}</h3>
                                <p class="mt-2 break-words text-sm leading-6 text-slate-500 dark:text-slate-400">{{ user?.email ?? '-' }}</p>
                                <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-300">{{ user?.department ?? 'Departemen belum diisi' }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                class="rounded-lg border border-blue-200 bg-white px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-200 dark:bg-white dark:text-blue-700 dark:hover:bg-blue-50"
                                @click="openModal('photo')"
                            >
                                Kelola Foto
                            </button>
                            <button
                                v-if="!isEmployee"
                                type="button"
                                class="rounded-lg border border-blue-200 bg-white px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-200 dark:bg-white dark:text-blue-700 dark:hover:bg-blue-50"
                                @click="openModal('information')"
                            >
                                Edit Informasi
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-blue-200 bg-white px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-200 dark:bg-white dark:text-blue-700 dark:hover:bg-blue-50"
                                @click="openModal('password')"
                            >
                                Ubah Password
                            </button>
                            <button
                                v-if="!isEmployee"
                                type="button"
                                class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-100 dark:border-red-200 dark:bg-red-50 dark:text-red-700 dark:hover:bg-red-100"
                                @click="openModal('delete')"
                            >
                                Hapus Akun
                            </button>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6 lg:p-8">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">{{ isEmployee ? 'Profil Karyawan' : 'Ringkasan Akun' }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">Informasi Diri</h3>
                        </div>
                        <p class="max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                            {{ isEmployee ? 'Data akun Anda bersifat informasi. Jika ada data yang keliru, hubungi admin untuk perbaikan.' : 'Gunakan tombol aksi di kiri untuk memperbarui data akun.' }}
                        </p>
                    </div>

                    <div class="mt-6 divide-y divide-slate-200 dark:divide-slate-800">
                        <div
                            v-for="item in profileItems"
                            :key="item.label"
                            class="grid gap-2 py-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ item.label }}</p>
                            <p class="break-words text-sm font-semibold leading-6 text-slate-900 dark:text-slate-100">{{ item.value }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <Modal :show="activeModal === 'photo'" max-width="2xl" @close="closeModal">
                <div class="p-5 sm:p-6">
                    <UpdateProfilePhotoForm />
                </div>
            </Modal>

            <Modal :show="activeModal === 'information'" max-width="3xl" @close="closeModal">
                <div class="p-5 sm:p-6">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </div>
            </Modal>

            <Modal :show="activeModal === 'password'" max-width="3xl" @close="closeModal">
                <div class="p-5 sm:p-6">
                    <UpdatePasswordForm />
                </div>
            </Modal>

            <Modal :show="activeModal === 'delete'" max-width="2xl" @close="closeModal">
                <div class="p-5 sm:p-6">
                    <DeleteUserForm />
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
