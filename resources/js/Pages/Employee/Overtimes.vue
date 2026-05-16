<script setup>
import { usePresenceCapture } from '@/composables/usePresenceCapture';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    setting: { type: Object, required: true },
    approvedTodayOvertimes: { type: Array, required: true },
    recentOvertimes: { type: Array, required: true },
});

const notify = useGlobalNotify();
const officeReady = computed(() => props.setting.is_configured);
const nextApprovedOvertime = computed(() => props.approvedTodayOvertimes[0] ?? null);

const getMakassarTime = () => {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Makassar',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date());

    const hour = parts.find((part) => part.type === 'hour')?.value;
    const minute = parts.find((part) => part.type === 'minute')?.value;
    const second = parts.find((part) => part.type === 'second')?.value;

    return hour && minute && second ? `${hour}:${minute}:${second}` : '00:00:00';
};

const timeToMinutes = (value) => {
    const [hour, minute, second = 0] = String(value ?? '').split(':').map(Number);

    return Number.isFinite(hour) && Number.isFinite(minute) && Number.isFinite(second)
        ? (hour * 60) + minute + (second / 60)
        : null;
};

const currentMakassarTime = ref(getMakassarTime());
const currentMakassarMinutes = computed(() => timeToMinutes(currentMakassarTime.value));
let currentTimeIntervalId = null;

const makassarTodayIso = () => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Makassar',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());

    const year = parts.find((part) => part.type === 'year')?.value;
    const month = parts.find((part) => part.type === 'month')?.value;
    const day = parts.find((part) => part.type === 'day')?.value;

    return year && month && day ? `${year}-${month}-${day}` : new Date().toISOString().slice(0, 10);
};

const {
    activeAction,
    cameraError,
    cameraLoading,
    cameraReady,
    captureSnapshot,
    currentPosition,
    ensureLocation,
    firstErrorMessage,
    locationError,
    locationLoading,
    startCamera,
    stopCamera,
    submitPresence,
    videoRef,
} = usePresenceCapture({
    notify,
    officeReady,
});

const overtimeForm = useForm({
    overtime_date: makassarTodayIso(),
    planned_start: '',
    planned_end: '',
    reason: '',
    request_photo: '',
});

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Makassar',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(Date.UTC(year, month - 1, day)));
};

const formatTime = (value) => {
    if (!value) {
        return '-';
    }

    return value.slice(0, 5);
};

const summaryCards = computed(() => [
    {
        label: 'Disetujui Hari Ini',
        value: String(props.approvedTodayOvertimes.length),
    },
    {
        label: 'Jadwal Terdekat',
        value: nextApprovedOvertime.value
            ? `${formatTime(nextApprovedOvertime.value.planned_start)} - ${formatTime(nextApprovedOvertime.value.planned_end)}`
            : '--:--',
    },
    {
        label: 'Riwayat Ditampilkan',
        value: String(props.recentOvertimes.length),
    },
    {
        label: 'Radius Kantor',
        value: `${props.setting.radius_meters ?? 100} m`,
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

const hasReachedOvertimeTime = (time) => {
    const plannedMinutes = timeToMinutes(time);

    return currentMakassarMinutes.value !== null
        && plannedMinutes !== null
        && currentMakassarMinutes.value >= plannedMinutes;
};

const canStartOvertime = (overtime) => !overtime.actual_start && hasReachedOvertimeTime(overtime.planned_start);

const canFinishOvertime = (overtime) => Boolean(overtime.actual_start)
    && !overtime.actual_end
    && hasReachedOvertimeTime(overtime.planned_end);

const overtimeAvailabilityHint = (overtime) => {
    if (!overtime.actual_start && !hasReachedOvertimeTime(overtime.planned_start)) {
        return `Absen mulai lembur dibuka mulai ${formatTime(overtime.planned_start)} WITA.`;
    }

    if (overtime.actual_start && !overtime.actual_end && !hasReachedOvertimeTime(overtime.planned_end)) {
        return `Absen selesai lembur dibuka mulai ${formatTime(overtime.planned_end)} WITA.`;
    }

    return '';
};

const submitOvertimeRequest = async () => {
    try {
        await startCamera();
        overtimeForm.request_photo = captureSnapshot();

        overtimeForm.post(route('employee.overtimes.store'), {
            preserveScroll: true,
            onSuccess: () => {
                notify.success('Pengajuan lembur berhasil dikirim.');
                overtimeForm.reset('planned_start', 'planned_end', 'reason', 'request_photo');
                overtimeForm.overtime_date = makassarTodayIso();
            },
            onError: (errors) => {
                notify.error(firstErrorMessage(errors));
            },
        });
    } catch (error) {
        if (error instanceof Error) {
            notify.error(error.message);
        }
    }
};

const handleStartOvertime = (overtimeId) => submitPresence({
    actionKey: `overtime-start-${overtimeId}`,
    routeName: 'employee.overtimes.start',
    successMessage: 'Absen mulai lembur berhasil disimpan.',
    routeParams: overtimeId,
});

const handleFinishOvertime = (overtimeId) => submitPresence({
    actionKey: `overtime-finish-${overtimeId}`,
    routeName: 'employee.overtimes.finish',
    successMessage: 'Absen selesai lembur berhasil disimpan.',
    routeParams: overtimeId,
});

onMounted(() => {
    currentTimeIntervalId = window.setInterval(() => {
        currentMakassarTime.value = getMakassarTime();
    }, 1000);
});

onBeforeUnmount(() => {
    if (currentTimeIntervalId) {
        window.clearInterval(currentTimeIntervalId);
    }

    stopCamera();
});
</script>

<template>
    <Head title="Lembur" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Lembur</h2>

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

            <section class="grid items-start gap-4 xl:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.1fr)]">
                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Pengajuan Lembur</p>
                    <h2 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Buat pengajuan baru</h2>

                    <form class="mt-4 space-y-4" @submit.prevent="submitOvertimeRequest">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tanggal</label>
                            <input
                                v-model="overtimeForm.overtime_date"
                                type="date"
                                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                            >
                            <p v-if="overtimeForm.errors.overtime_date" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.overtime_date }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Mulai</label>
                                <input
                                    v-model="overtimeForm.planned_start"
                                    type="time"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                                >
                                <p v-if="overtimeForm.errors.planned_start" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.planned_start }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Selesai</label>
                                <input
                                    v-model="overtimeForm.planned_end"
                                    type="time"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                                >
                                <p v-if="overtimeForm.errors.planned_end" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.planned_end }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Alasan</label>
                            <textarea
                                v-model="overtimeForm.reason"
                                rows="4"
                                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                                placeholder="Contoh: penyelesaian target harian, penutupan laporan, atau kebutuhan operasional lapangan."
                            ></textarea>
                            <p v-if="overtimeForm.errors.reason" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.reason }}</p>
                        </div>

                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            Saat pengajuan dikirim, kamera akan mengambil foto untuk bukti pengajuan lembur.
                        </p>
                        <p v-if="overtimeForm.errors.request_photo" class="text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.request_photo }}</p>

                        <button
                            type="submit"
                            :disabled="overtimeForm.processing"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ overtimeForm.processing ? 'Mengirim Pengajuan...' : 'Kirim Pengajuan Lembur' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Ruang Presensi Lembur</p>
                            <h2 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Kamera dan GPS untuk pengajuan, mulai, atau selesai lembur</h2>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                :disabled="cameraLoading"
                                @click="startCamera"
                            >
                                {{ cameraLoading ? 'Mengaktifkan...' : cameraReady ? 'Kamera Aktif' : 'Aktifkan Kamera' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                :disabled="locationLoading"
                                @click="ensureLocation().catch(() => {})"
                            >
                                {{ locationLoading ? 'Mengambil GPS...' : currentPosition ? 'Perbarui Lokasi' : 'Ambil Lokasi' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="relative overflow-hidden rounded-lg border border-slate-200 bg-slate-950 dark:border-slate-800">
                            <video ref="videoRef" autoplay muted playsinline class="aspect-[4/3] w-full object-cover" />
                            <div v-if="!cameraReady" class="absolute inset-0 grid place-items-center bg-slate-950/85 px-6 text-center text-sm text-slate-300">
                                <div>
                                    <p class="font-medium text-white">Pratinjau kamera belum aktif</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-slate-500 dark:text-slate-400">Lokasi Saat Ini</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">
                                {{ currentPosition ? `${currentPosition.latitude.toFixed(6)}, ${currentPosition.longitude.toFixed(6)}` : 'Belum diambil' }}
                            </p>
                            <p class="mt-1 text-slate-500 dark:text-slate-400">
                                Akurasi: {{ currentPosition?.accuracy ? `${currentPosition.accuracy} m` : '-' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="cameraError || locationError || !officeReady" class="mt-3 space-y-2">
                        <p v-if="cameraError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                            {{ cameraError }}
                        </p>
                        <p v-if="locationError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                            {{ locationError }}
                        </p>
                        <p v-if="!officeReady" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            Admin belum mengisi latitude dan longitude kantor, jadi validasi radius belum bisa dijalankan.
                        </p>
                    </div>
                </section>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Presensi Lembur</p>
                        <h2 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Pengajuan disetujui hari ini</h2>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                        {{ approvedTodayOvertimes.length }} aktif
                    </span>
                </div>

                <div v-if="approvedTodayOvertimes.length" class="mt-4 space-y-3">
                    <article
                        v-for="overtime in approvedTodayOvertimes"
                        :key="overtime.id"
                        class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ overtime.reason || '-' }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                {{ statusLabel(overtime.approval_status) }}
                            </span>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                <p class="text-xs uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500">Mulai Lembur</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(overtime.actual_start) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                <p class="text-xs uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500">Selesai Lembur</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(overtime.actual_end) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canStartOvertime(overtime) || activeAction === `overtime-finish-${overtime.id}` || !officeReady"
                                @click="handleStartOvertime(overtime.id)"
                            >
                                {{ activeAction === `overtime-start-${overtime.id}` ? 'Memproses Mulai...' : 'Absen Mulai Lembur' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canFinishOvertime(overtime) || activeAction === `overtime-start-${overtime.id}` || !officeReady"
                                @click="handleFinishOvertime(overtime.id)"
                            >
                                {{ activeAction === `overtime-finish-${overtime.id}` ? 'Memproses Selesai...' : 'Absen Selesai Lembur' }}
                            </button>
                        </div>

                        <p v-if="overtimeAvailabilityHint(overtime)" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            {{ overtimeAvailabilityHint(overtime) }}
                        </p>
                    </article>
                </div>

                <div v-else class="mt-4 rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    Belum ada lembur yang disetujui untuk hari ini.
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Riwayat Lembur</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pe-3 font-medium">Tanggal</th>
                                <th class="py-2 pe-3 font-medium">Rencana</th>
                                <th class="py-2 pe-3 font-medium">Aktual</th>
                                <th class="py-2 pe-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                            <tr v-for="overtime in recentOvertimes" :key="overtime.id">
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</td>
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}</td>
                                <td class="py-3 pe-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(overtime.approval_status)">
                                        {{ statusLabel(overtime.approval_status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!recentOvertimes.length">
                                <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada riwayat lembur.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
