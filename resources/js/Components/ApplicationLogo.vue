<script setup>
defineProps({
    large: {
        type: Boolean,
        default: false,
    },
    light: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="grid shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
            :class="large ? 'h-16 w-16' : 'h-12 w-12'"
        >
            <img
                src="/images/logo.jpeg"
                alt="Logo PT. ABDARA BRM TAMBANG"
                class="h-full w-full object-cover"
            >
        </div>
        <span
            class="min-w-0 break-words font-black leading-tight"
            :class="[large ? 'text-2xl sm:text-3xl' : 'text-lg', light ? 'text-white' : 'text-slate-900 dark:text-slate-100']"
        >
            PT. ABDARA BRM TAMBANG
        </span>
    </div>
</template>
