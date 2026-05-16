<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const isSidebarCollapsed = ref(false);
const page = usePage();
let sidebarResizeTimeoutId = null;
let sidebarResizeFrameId = null;

const isAdmin = computed(() => page.props.auth?.user?.role === 'Admin');
const homeRouteName = computed(() => (isAdmin.value ? 'dashboard' : 'home'));
const sidebarStorageKey = computed(() => (isAdmin.value ? 'admin-sidebar-collapsed' : 'employee-sidebar-collapsed'));

const navLinks = computed(() => {
    if (isAdmin.value) {
        return [
            { name: 'dashboard', label: 'Dashboard' },
            { name: 'admin.employees.index', label: 'Karyawan' },
            { name: 'admin.settings.index', label: 'Pengaturan' },
            { name: 'admin.attendances.index', label: 'Presensi' },
            { name: 'admin.overtimes.index', label: 'Lembur' },
            { name: 'admin.leaves.index', label: 'Izin' },
            { name: 'admin.outside-duties.index', label: 'Tugas Luar' },
            { name: 'admin.reports.index', label: 'Laporan' },
        ];
    }

    return [
        { name: 'home', label: 'Beranda' },
        { name: 'employee.attendance.index', label: 'Presensi' },
        { name: 'employee.overtimes.index', label: 'Lembur' },
        { name: 'employee.leaves.index', label: 'Izin' },
        {
            name: 'employee.outside-duties.index',
            label: 'Tugas Luar',
            active: ['employee.outside-duties.*'],
        },
    ];
});

const isLinkActive = (link) => {
    const activeRoutes = link.active ?? [link.name];

    return activeRoutes.some((routeName) => route().current(routeName));
};

const currentNav = computed(() => navLinks.value.find((link) => isLinkActive(link)) ?? navLinks.value[0]);

const displayName = computed(() => {
    const user = page.props.auth?.user;

    return user?.full_name ?? user?.name ?? 'User';
});

const userPhotoUrl = computed(() => page.props.auth?.user?.profile_photo_url ?? null);

const userInitials = computed(() => {
    const name = displayName.value.trim();

    if (!name) {
        return 'U';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const toggleNavigationDropdown = () => {
    showingNavigationDropdown.value = !showingNavigationDropdown.value;
};

const closeNavigationDropdown = () => {
    showingNavigationDropdown.value = false;
};

const persistSidebarState = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(sidebarStorageKey.value, String(isSidebarCollapsed.value));
};

const emitAdminLayoutResize = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new Event('resize'));
    window.dispatchEvent(new CustomEvent('admin-layout:resize', {
        detail: {
            sidebarCollapsed: isSidebarCollapsed.value,
        },
    }));
};

const scheduleAdminLayoutResize = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (sidebarResizeFrameId) {
        window.cancelAnimationFrame(sidebarResizeFrameId);
    }

    if (sidebarResizeTimeoutId) {
        window.clearTimeout(sidebarResizeTimeoutId);
    }

    sidebarResizeFrameId = window.requestAnimationFrame(() => {
        emitAdminLayoutResize();

        sidebarResizeTimeoutId = window.setTimeout(() => {
            emitAdminLayoutResize();
        }, 120);
    });
};

const toggleDesktopSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    persistSidebarState();
    scheduleAdminLayoutResize();
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    isSidebarCollapsed.value = window.localStorage.getItem(sidebarStorageKey.value) === 'true';
    scheduleAdminLayoutResize();
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    if (sidebarResizeTimeoutId) {
        window.clearTimeout(sidebarResizeTimeoutId);
    }

    if (sidebarResizeFrameId) {
        window.cancelAnimationFrame(sidebarResizeFrameId);
    }
});
</script>

<template>
    <div class="min-h-screen mining-dashboard-bg text-slate-900 transition-colors">
        <div class="min-h-screen">
            <aside
                v-show="!isSidebarCollapsed"
                class="hidden border-r border-blue-100 bg-white px-4 py-6 xl:fixed xl:inset-y-0 xl:left-0 xl:z-20 xl:flex xl:h-screen xl:w-64 xl:flex-col xl:overflow-y-auto dark:border-blue-200 dark:bg-white"
            >
                <Link :href="route(homeRouteName)" class="inline-flex">
                    <ApplicationLogo class="max-w-full" />
                </Link>

                <nav class="mt-6 space-y-1">
                    <Link
                        v-for="link in navLinks"
                        :key="link.name"
                        :href="route(link.name)"
                        class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="isLinkActive(link)
                            ? 'bg-blue-700 text-white dark:bg-blue-700 dark:text-white'
                            : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700 dark:text-slate-600 dark:hover:bg-blue-50 dark:hover:text-blue-700'"
                        @click="closeNavigationDropdown"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="mt-auto border-t border-blue-100 pt-4 dark:border-blue-100">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-blue-50 text-xs font-semibold text-blue-700 dark:bg-blue-50 dark:text-blue-700">
                            <img v-if="userPhotoUrl" :src="userPhotoUrl" alt="Foto profil" class="h-full w-full object-cover">
                            <span v-else>{{ userInitials }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ displayName }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $page.props.auth.user.email }}</p>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-2">
                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex items-center justify-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-200 dark:text-blue-700 dark:hover:bg-blue-50"
                        >
                            Profil
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-600 bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:border-red-600 dark:bg-red-600 dark:text-white dark:hover:bg-red-700"
                        >
                            Keluar
                        </Link>
                    </div>
                </div>
            </aside>

            <div
                class="flex min-h-screen flex-col transition-[padding] duration-300"
                :class="isSidebarCollapsed ? 'xl:pl-0' : 'xl:pl-64'"
            >
                <header class="sticky top-0 z-30 border-b border-blue-100 bg-white/95 backdrop-blur dark:border-blue-100 dark:bg-white/95">
                    <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-blue-200 bg-white text-blue-700 transition hover:bg-blue-50 xl:hidden dark:border-blue-200 dark:bg-white dark:text-blue-700 dark:hover:bg-blue-50"
                                @click="toggleNavigationDropdown"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path
                                        :d="showingNavigationDropdown ? 'M6 6L18 18M6 18L18 6' : 'M4 7H20M4 12H20M4 17H14'"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>

                            <button
                                type="button"
                                class="hidden h-10 w-10 items-center justify-center rounded-lg border border-blue-200 bg-white text-blue-700 transition hover:bg-blue-50 xl:inline-flex dark:border-blue-200 dark:bg-white dark:text-blue-700 dark:hover:bg-blue-50"
                                @click="toggleDesktopSidebar"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path
                                        :d="isSidebarCollapsed ? 'M4 12H20M14 6L20 12L14 18' : 'M4 12H20M10 6L4 12L10 18'"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>

                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ currentNav?.label }}</p>
                        </div>

                        <div aria-hidden="true"></div>
                    </div>

                    <div v-if="showingNavigationDropdown" class="border-t border-blue-100 bg-white px-4 py-3 xl:hidden dark:border-blue-100 dark:bg-white">
                        <div class="space-y-1">
                            <Link
                                v-for="link in navLinks"
                                :key="link.name"
                                :href="route(link.name)"
                                class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="isLinkActive(link)
                                    ? 'bg-blue-700 text-white dark:bg-blue-700 dark:text-white'
                                    : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700 dark:text-slate-600 dark:hover:bg-blue-50 dark:hover:text-blue-700'"
                                @click="closeNavigationDropdown"
                            >
                                {{ link.label }}
                            </Link>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex items-center justify-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 dark:border-blue-200 dark:text-blue-700"
                                @click="closeNavigationDropdown"
                            >
                                Profil
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="inline-flex items-center justify-center rounded-lg border border-red-600 bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:border-red-600 dark:bg-red-600 dark:text-white dark:hover:bg-red-700"
                            >
                                Keluar
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="mining-content-bg flex-1 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4">
                        <section
                            v-if="$slots.header"
                            class="rounded-lg border border-blue-100 bg-white p-4 dark:border-blue-100 dark:bg-white"
                        >
                            <slot name="header" />
                        </section>

                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
