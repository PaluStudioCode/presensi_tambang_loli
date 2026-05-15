<script setup>
import Modal from '@/Components/Modal.vue';
import { useGlobalConfirm } from '@/composables/useGlobalConfirm';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    attendanceToday: {
        type: Object,
        required: true,
    },
    recentAttendances: {
        type: Array,
        required: true,
    },
    pendingOvertimes: {
        type: Array,
        required: true,
    },
    recentOvertimes: {
        type: Array,
        required: true,
    },
});

const actionLoadingId = ref(null);
const actionLoadingType = ref(null);
const showPhotoModal = ref(false);
const selectedPhotoSrc = ref('');
const selectedPhotoTitle = ref('');
const selectedPhotoMeta = ref('');
const confirm = useGlobalConfirm();
const notify = useGlobalNotify();

const attendanceRate = computed(() => {
    if (!props.summary.totalEmployees) {
        return 0;
    }

    return Math.round((props.summary.presentEmployeesToday / props.summary.totalEmployees) * 100);
});

const summaryCards = computed(() => [
    {
        label: 'Total Karyawan',
        value: props.summary.totalEmployees,
    },
    {
        label: 'Kehadiran Hari Ini',
        value: `${attendanceRate.value}%`,
    },
    {
        label: 'Lembur Menunggu',
        value: props.summary.pendingOvertimes,
    },
    {
        label: 'Jam Lembur Disetujui',
        value: `${props.summary.approvedOvertimeHoursThisMonth} jam`,
    },
]);

const attendanceMetrics = computed(() => [
    {
        label: 'Absen Masuk',
        value: props.attendanceToday.clockedIn,
    },
    {
        label: 'Absen Pulang',
        value: props.attendanceToday.clockedOut,
    },
    {
        label: 'Terlambat',
        value: props.attendanceToday.lateCheckIn,
    },
]);

const overtimeHighlights = computed(() => [
    {
        label: 'Disetujui',
        value: props.summary.approvedOvertimesThisMonth,
        tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    {
        label: 'Ditolak',
        value: props.summary.rejectedOvertimesThisMonth,
        tone: 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
    },
]);

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

const statusClass = (status) => {
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

const clockInStatusClass = (status) => {
    if (status === 'Lambat') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }

    if (status === 'Tepat Waktu') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
};

const openPhotoModal = (attendance, type) => {
    const src = type === 'in' ? attendance.clock_in_photo : attendance.clock_out_photo;

    if (!src) {
        return;
    }

    selectedPhotoSrc.value = src;
    selectedPhotoTitle.value = type === 'in' ? 'Foto Absen Masuk' : 'Foto Absen Pulang';
    selectedPhotoMeta.value = `${attendance.employee_name} • ${formatDate(attendance.date)}`;
    showPhotoModal.value = true;
};

const openOvertimeRequestPhotoModal = (overtime) => {
    if (!overtime.overtime_request_photo) {
        return;
    }

    selectedPhotoSrc.value = overtime.overtime_request_photo;
    selectedPhotoTitle.value = 'Foto Pengajuan Lembur';
    selectedPhotoMeta.value = `${overtime.employee_name} - ${formatDate(overtime.overtime_date)}`;
    showPhotoModal.value = true;
};

const closePhotoModal = () => {
    showPhotoModal.value = false;
    selectedPhotoSrc.value = '';
    selectedPhotoTitle.value = '';
    selectedPhotoMeta.value = '';
};

const processOvertime = async (id, action) => {
    if (action === 'reject') {
        const shouldReject = await confirm({
            title: 'Tolak Pengajuan Lembur?',
            message: 'Pengajuan ini akan ditandai sebagai ditolak.',
            confirmText: 'Ya, Tolak',
            cancelText: 'Batal',
            variant: 'danger',
        });

        if (!shouldReject) {
            return;
        }
    }

    actionLoadingId.value = id;
    actionLoadingType.value = action;

    router.patch(route(`admin.overtimes.${action}`, id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const message = action === 'approve'
                ? 'Pengajuan lembur berhasil disetujui.'
                : 'Pengajuan lembur berhasil ditolak.';
            notify.success(message);
        },
        onError: () => {
            const message = action === 'approve'
                ? 'Gagal menyetujui pengajuan lembur.'
                : 'Gagal menolak pengajuan lembur.';
            notify.error(message);
        },
        onFinish: () => {
            actionLoadingId.value = null;
            actionLoadingType.value = null;
        },
    });
};
</script>

<template>
    <Head title="Dasbor Admin" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Utama</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Nilai utama untuk memantau kondisi operasional hari ini.</p>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in summaryCards"
                        :key="card.label"
                        class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                    >
                        <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ card.value }}</p>
                    </article>
                </div>
            </section>

            <section class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Monitoring Presensi Hari Ini</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ props.summary.presentEmployeesToday }} / {{ props.summary.totalEmployees }} hadir</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            {{ attendanceRate }}%
                        </span>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div
                            v-for="metric in attendanceMetrics"
                            :key="metric.label"
                            class="rounded-lg border border-slate-200 px-3 py-3 text-sm dark:border-slate-700"
                        >
                            <p class="text-slate-500 dark:text-slate-400">{{ metric.label }}</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ metric.value }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Lembur</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="item in overtimeHighlights"
                            :key="item.label"
                            class="rounded-lg px-3 py-3 text-sm"
                            :class="item.tone"
                        >
                            <p>{{ item.label }}</p>
                            <p class="mt-1 text-2xl font-semibold">{{ item.value }}</p>
                        </div>
                    </div>
                    <div class="mt-3 rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        Menunggu: <span class="font-semibold">{{ props.summary.pendingOvertimes }}</span>
                    </div>
                </div>
            </section>

            <section class="grid items-stretch gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.95fr)]">
                <div class="flex h-[32rem] min-h-0 flex-col rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Presensi Terakhir</h3>
                        <Link
                            :href="route('admin.attendances.index')"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Lihat Semua
                        </Link>
                    </div>

                    <div class="mt-3 min-h-0 flex-1 overflow-auto pe-1">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                <tr>
                                    <th class="py-2 pe-3 font-medium">Tanggal</th>
                                    <th class="py-2 pe-3 font-medium">Karyawan</th>
                                    <th class="py-2 pe-3 font-medium">Masuk</th>
                                    <th class="py-2 pe-3 font-medium">Status</th>
                                    <th class="py-2 pe-3 font-medium">Pulang</th>
                                    <th class="py-2 pe-3 font-medium">Lokasi</th>
                                    <th class="py-2 pe-3 font-medium">Foto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="attendance in recentAttendances" :key="attendance.id">
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-3 pe-3">
                                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ attendance.employee_name }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ attendance.id_number ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="clockInStatusClass(attendance.clock_in_status)">
                                            {{ attendance.clock_in_status ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-3 pe-3">
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_in_location ?? '-'">Masuk: {{ attendance.clock_in_location ?? '-' }}</p>
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_out_location ?? '-'">Pulang: {{ attendance.clock_out_location ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-3">
                                        <div class="flex flex-col gap-2">
                                            <button
                                                v-if="attendance.clock_in_photo"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                @click="openPhotoModal(attendance, 'in')"
                                            >
                                                Foto Masuk
                                            </button>
                                            <button
                                                v-if="attendance.clock_out_photo"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                @click="openPhotoModal(attendance, 'out')"
                                            >
                                                Foto Pulang
                                            </button>
                                            <span v-if="!attendance.clock_in_photo && !attendance.clock_out_photo" class="text-xs text-slate-500 dark:text-slate-400">-</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="recentAttendances.length === 0">
                                    <td colspan="7" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data presensi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex h-[32rem] min-h-0 flex-col rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Lembur Menunggu</h3>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            {{ pendingOvertimes.length }} antrean
                        </span>
                    </div>

                    <div v-if="pendingOvertimes.length > 0" class="mt-3 min-h-0 flex-1 space-y-2 overflow-y-auto pe-1">
                        <article
                            v-for="overtime in pendingOvertimes"
                            :key="overtime.id"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ overtime.employee_name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ overtime.id_number ?? '-' }}</p>
                                </div>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(overtime.overtime_date) }}</span>
                            </div>

                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                {{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ overtime.reason ?? '-' }}</p>

                            <button
                                v-if="overtime.overtime_request_photo"
                                type="button"
                                class="mt-3 inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="openOvertimeRequestPhotoModal(overtime)"
                            >
                                Lihat Foto Pengajuan
                            </button>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                    :disabled="actionLoadingId === overtime.id"
                                    @click="processOvertime(overtime.id, 'approve')"
                                >
                                    <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'approve'">Memproses...</span>
                                    <span v-else>Setujui</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-rose-600 dark:hover:bg-rose-500"
                                    :disabled="actionLoadingId === overtime.id"
                                    @click="processOvertime(overtime.id, 'reject')"
                                >
                                    <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'reject'">Memproses...</span>
                                    <span v-else>Tolak</span>
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="mt-3 flex min-h-0 flex-1 items-center justify-center rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        Tidak ada pengajuan lembur yang menunggu persetujuan.
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Riwayat Lembur Terbaru</h3>
                    <Link
                        :href="route('admin.reports.index')"
                        class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Buka Laporan
                    </Link>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pe-3 font-medium">Tanggal</th>
                                <th class="py-2 pe-3 font-medium">Karyawan</th>
                                <th class="py-2 pe-3 font-medium">Status</th>
                                <th class="py-2 pe-3 font-medium">Rencana</th>
                                <th class="py-2 pe-3 font-medium">Aktual</th>
                                <th class="py-2 pe-3 font-medium">Foto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                            <tr v-for="overtime in recentOvertimes" :key="overtime.id">
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                <td class="py-3 pe-3 font-semibold text-slate-900 dark:text-slate-100">{{ overtime.employee_name }}</td>
                                <td class="py-3 pe-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(overtime.approval_status)">
                                        {{ statusLabel(overtime.approval_status) }}
                                    </span>
                                </td>
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</td>
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}</td>
                                <td class="py-3 pe-3">
                                    <button
                                        v-if="overtime.overtime_request_photo"
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        @click="openOvertimeRequestPhotoModal(overtime)"
                                    >
                                        Foto Pengajuan
                                    </button>
                                    <span v-else class="text-xs text-slate-500 dark:text-slate-400">-</span>
                                </td>
                            </tr>
                            <tr v-if="recentOvertimes.length === 0">
                                <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data lembur.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <Modal :show="showPhotoModal" max-width="4xl" @close="closePhotoModal">
                <div class="p-4 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ selectedPhotoTitle }}</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">{{ selectedPhotoMeta }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="closePhotoModal"
                        >
                            Tutup
                        </button>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
                        <img
                            v-if="selectedPhotoSrc"
                            :src="selectedPhotoSrc"
                            :alt="selectedPhotoTitle"
                            class="max-h-[70vh] w-full object-contain"
                        />
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
