<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-200 dark:bg-white dark:text-blue-700 dark:hover:bg-blue-50 dark:focus:ring-blue-500/10"
    >
        <slot />
    </button>
</template>
