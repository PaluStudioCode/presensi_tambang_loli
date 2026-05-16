<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="w-full rounded-lg border border-blue-100 bg-white px-4 py-3 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-blue-50 disabled:text-slate-500 dark:border-blue-200 dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-500 dark:focus:ring-blue-500/10 dark:disabled:bg-blue-50 dark:disabled:text-slate-500"
        v-model="model"
        ref="input"
    />
</template>
