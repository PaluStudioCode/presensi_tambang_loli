<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Hapus Akun</h2>

            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Setelah akun dihapus, semua data terkait akan terhapus permanen. Sebelum menghapus akun,
                pastikan Anda sudah menyimpan data yang masih dibutuhkan.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Hapus Akun</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                    Apakah Anda yakin ingin menghapus akun?
                </h2>

                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Setelah akun dihapus, semua data terkait akan terhapus permanen. Masukkan password Anda
                    untuk mengonfirmasi penghapusan akun.
                </p>

                <div class="mt-6">
                    <InputLabel for="password" value="Password" class="sr-only" />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <SecondaryButton @click="closeModal"> Batal </SecondaryButton>

                    <DangerButton
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Hapus Akun
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
