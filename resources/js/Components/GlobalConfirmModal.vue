<script setup>
import Modal from '@/Components/Modal.vue';
import { confirmDialogController } from '@/composables/useGlobalConfirm';
import { computed } from 'vue';

const { state, confirmNow, cancelNow } = confirmDialogController;

const confirmButtonClass = computed(() => {
    if (state.variant === 'warning') {
        return 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700';
    }

    if (state.variant === 'primary') {
        return 'bg-blue-700 text-white hover:bg-blue-800 dark:bg-blue-700 dark:text-white dark:hover:bg-blue-800';
    }

    return 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700';
});
</script>

<template>
    <Modal :show="state.show" :closeable="!state.isProcessing" max-width="md" @close="cancelNow">
        <div class="p-4 sm:p-6">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ state.title }}
            </h3>
            <p class="mt-2 text-sm text-slate-600 whitespace-pre-line dark:text-slate-300">
                {{ state.message }}
            </p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-blue-200 px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-200 dark:text-blue-700 dark:hover:bg-blue-50"
                    :disabled="state.isProcessing"
                    :class="state.isProcessing ? 'cursor-not-allowed opacity-60' : ''"
                    @click="cancelNow"
                >
                    {{ state.cancelText }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-70"
                    :class="confirmButtonClass"
                    :disabled="state.isProcessing"
                    @click="confirmNow"
                >
                    <span
                        v-if="state.isProcessing"
                        class="me-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
                        aria-hidden="true"
                    />
                    {{ state.isProcessing ? state.loadingText : state.confirmText }}
                </button>
            </div>
        </div>
    </Modal>
</template>
