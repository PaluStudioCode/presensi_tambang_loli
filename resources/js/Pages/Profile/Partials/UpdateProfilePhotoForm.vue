<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const selectedPhotoUrl = ref(null);

const form = useForm({
    profile_photo: null,
});

const deleteForm = useForm({});

const displayName = computed(() => user.value?.full_name ?? 'User');
const userInitials = computed(() => displayName.value
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || 'U');

const previewUrl = computed(() => selectedPhotoUrl.value || user.value?.profile_photo_url || null);

const handlePhotoChange = (event) => {
    const [file] = event.target.files ?? [];
    form.profile_photo = file ?? null;

    if (selectedPhotoUrl.value) {
        URL.revokeObjectURL(selectedPhotoUrl.value);
    }

    selectedPhotoUrl.value = file ? URL.createObjectURL(file) : null;
};

const submit = () => {
    form.post(route('profile.photo.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('profile_photo');

            if (selectedPhotoUrl.value) {
                URL.revokeObjectURL(selectedPhotoUrl.value);
                selectedPhotoUrl.value = null;
            }
        },
    });
};

const removePhoto = () => {
    deleteForm.delete(route('profile.photo.destroy'), {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    if (selectedPhotoUrl.value) {
        URL.revokeObjectURL(selectedPhotoUrl.value);
    }
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Foto Profil</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Upload foto profil Anda sendiri.
            </p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div class="flex flex-wrap items-center gap-4">
                <div class="grid h-24 w-24 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 text-xl font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Foto profil"
                        class="h-full w-full object-cover"
                    >
                    <span v-else>{{ userInitials }}</span>
                </div>

                <div class="min-w-0 flex-1">
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="block w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 file:me-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white file:transition hover:file:bg-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:file:bg-slate-800 dark:file:text-slate-100 dark:hover:file:bg-slate-700"
                        @change="handlePhotoChange"
                    >
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Format JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                    <InputError class="mt-2" :message="form.errors.profile_photo" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <PrimaryButton :disabled="form.processing || !form.profile_photo">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Foto' }}
                </PrimaryButton>

                <SecondaryButton
                    v-if="user?.profile_photo_url"
                    type="button"
                    :disabled="deleteForm.processing"
                    @click="removePhoto"
                >
                    {{ deleteForm.processing ? 'Menghapus...' : 'Hapus Foto' }}
                </SecondaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful || deleteForm.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-300">
                        Tersimpan.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
