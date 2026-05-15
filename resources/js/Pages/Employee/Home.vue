<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    setting: { type: Object, required: true },
    todayAttendance: { type: Object, default: null },
    approvedTodayOvertimes: { type: Array, required: true },
    recentAttendances: { type: Array, required: true },
    recentOvertimes: { type: Array, required: true },
});

const officeReady = computed(() => props.setting.is_configured);
const nextApprovedOvertime = computed(() => props.approvedTodayOvertimes[0] ?? null);

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
};

const formatTime = (value) => {
    if (!value) {
        return '-';
    }

    return value.slice(0, 5);
};

const summaryCards = computed(() => [
    {
        label: 'Status Hari Ini',
        value: props.todayAttendance?.clock_out_at ? 'Selesai Shift' : props.todayAttendance?.clock_in_at ? 'Sedang Bekerja' : 'Belum Presensi',
    },
    {
        label: 'Jadwal Kerja',
        value: `${props.setting.check_in_time ?? '--:--'} - ${props.setting.check_out_time ?? '--:--'}`,
    },
    {
        label: 'Radius Kantor',
        value: officeReady.value ? `${props.setting.radius_meters ?? 100} m` : 'Nonaktif',
    },
    {
        label: 'Lembur Disetujui',
        value: String(props.approvedTodayOvertimes.length),
    },
]);

const statusBadgeClass = (status) => {
    if (status === 'Approved') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (status === 'Rejected') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
};

const statusLabel = (status) => ({
    Pending: 'Menunggu',
    Approved: 'Disetujui',
    Rejected: 'Ditolak',
}[status] ?? status);
</script>

<template>
    <Head title="Beranda" />

    <AuthenticatedLayout>
        <div class="min-w-0 space-y-4 overflow-hidden">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Aktivitas</h2>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in summaryCards"
                        :key="card.label"
                        class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                    >
                        <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                        <p class="mt-2 break-words text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ card.value }}</p>
                    </article>
                </div>
            </section>

            <section class="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Presensi Hari Ini</h3>
                        </div>
                        <Link
                            :href="route('employee.attendance.index')"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Presensi
                        </Link>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div class="min-w-0 rounded-lg border border-slate-200 px-3 py-3 text-sm dark:border-slate-700">
                            <p class="text-slate-500 dark:text-slate-400">Masuk</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(todayAttendance?.clock_in_at) }}</p>
                        </div>
                        <div class="min-w-0 rounded-lg border border-slate-200 px-3 py-3 text-sm dark:border-slate-700">
                            <p class="text-slate-500 dark:text-slate-400">Pulang</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(todayAttendance?.clock_out_at) }}</p>
                        </div>
                        <div class="min-w-0 rounded-lg border border-slate-200 px-3 py-3 text-sm dark:border-slate-700">
                            <p class="text-slate-500 dark:text-slate-400">Validasi</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ officeReady ? 'Kantor' : 'Nonaktif' }}</p>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Lembur</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div class="min-w-0 rounded-lg bg-emerald-100 px-3 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <p>Disetujui Hari Ini</p>
                            <p class="mt-1 text-2xl font-semibold">{{ approvedTodayOvertimes.length }}</p>
                        </div>
                        <div class="min-w-0 rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <p>Jadwal Terdekat</p>
                            <p class="mt-1 break-words text-2xl font-semibold">
                                {{ nextApprovedOvertime ? `${formatTime(nextApprovedOvertime.planned_start)} - ${formatTime(nextApprovedOvertime.planned_end)}` : '--:--' }}
                            </p>
                        </div>
                    </div>
                    <Link
                        :href="route('employee.overtimes.index')"
                        class="mt-3 inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Lembur
                    </Link>
                </div>
            </section>

            <section class="grid min-w-0 items-stretch gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
                <section class="flex min-h-0 min-w-0 flex-col rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:h-[28rem]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Presensi Terakhir</h2>
                        <Link
                            :href="route('employee.attendance.index')"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Semua
                        </Link>
                    </div>

                    <div class="mt-3 space-y-2 sm:hidden">
                        <article
                            v-for="attendance in recentAttendances"
                            :key="attendance.id"
                            class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/60"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ formatDate(attendance.date) }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ formatTime(attendance.clock_in_at) }} - {{ formatTime(attendance.clock_out_at) }}
                                </p>
                            </div>
                            <p class="mt-2 break-all text-slate-500 dark:text-slate-400">Masuk: {{ attendance.clock_in_location || '-' }}</p>
                            <p class="mt-1 break-all text-slate-500 dark:text-slate-400">Pulang: {{ attendance.clock_out_location || '-' }}</p>
                        </article>
                        <div v-if="!recentAttendances.length" class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Belum ada data presensi.
                        </div>
                    </div>

                    <div class="mt-3 hidden min-h-0 flex-1 overflow-auto sm:block">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="py-2 pe-3 font-medium">Tanggal</th>
                                    <th class="py-2 pe-3 font-medium">Masuk</th>
                                    <th class="py-2 pe-3 font-medium">Pulang</th>
                                    <th class="py-2 pe-3 font-medium">Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="attendance in recentAttendances" :key="attendance.id">
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-3 pe-3">
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_in_location || '-'">Masuk: {{ attendance.clock_in_location || '-' }}</p>
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_out_location || '-'">Pulang: {{ attendance.clock_out_location || '-' }}</p>
                                    </td>
                                </tr>
                                <tr v-if="!recentAttendances.length">
                                    <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data presensi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="flex min-h-0 min-w-0 flex-col rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:h-[28rem]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Lembur Terbaru</h2>
                        <Link
                            :href="route('employee.overtimes.index')"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Semua
                        </Link>
                    </div>

                    <div class="mt-3 min-h-0 flex-1 space-y-2 overflow-y-auto">
                        <article
                            v-for="overtime in recentOvertimes"
                            :key="overtime.id"
                            class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ formatDate(overtime.overtime_date) }}</p>
                                    <p class="break-words text-sm text-slate-500 dark:text-slate-400">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</p>
                                </div>
                                <span class="inline-flex shrink-0 rounded-full px-2 py-1 text-xs font-semibold" :class="statusBadgeClass(overtime.approval_status)">
                                    {{ statusLabel(overtime.approval_status) }}
                                </span>
                            </div>
                        </article>
                        <div v-if="!recentOvertimes.length" class="flex min-h-0 flex-1 items-center justify-center rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Belum ada riwayat lembur.
                        </div>
                    </div>
                </section>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
