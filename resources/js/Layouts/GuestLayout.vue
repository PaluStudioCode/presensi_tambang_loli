<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-slate-950 bg-cover bg-center px-4 py-6 text-slate-900 transition-colors dark:text-slate-100" style="background-image: url('/storage/bg-login.jpeg');">
        <div class="absolute inset-0 bg-slate-950/70"></div>

        <div class="z-10 mb-8 max-w-md transform transition-transform duration-500 hover:scale-105">
            <Link href="/">
                <ApplicationLogo large light class="w-full drop-shadow" />
            </Link>
        </div>

        <div
            class="relative z-10 w-full border border-white/20 bg-white/90 px-8 py-10 shadow-xl backdrop-blur-2xl transition-colors sm:max-w-md sm:rounded-3xl dark:bg-slate-900/85 dark:border-slate-700"
        >
            <slot />
        </div>
    </div>
</template>
