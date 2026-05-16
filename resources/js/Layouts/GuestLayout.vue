<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-blue-950 bg-cover bg-center px-4 py-6 text-slate-900 transition-colors dark:text-slate-900" style="background-image: url('/storage/bg-login.jpeg');">
        <div class="absolute inset-0 bg-blue-950/65"></div>

        <div class="z-10 mb-8 max-w-md transform transition-transform duration-500 hover:scale-105">
            <Link href="/">
                <ApplicationLogo large light class="w-full" />
            </Link>
        </div>

        <div
            class="relative z-10 w-full border border-blue-100 bg-white/95 px-8 py-10 backdrop-blur-2xl transition-colors sm:max-w-md sm:rounded-3xl dark:border-blue-100 dark:bg-white/95"
        >
            <slot />
        </div>
    </div>
</template>
