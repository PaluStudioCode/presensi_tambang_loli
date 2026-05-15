<script setup>
import { usePresenceCapture } from '@/composables/usePresenceCapture';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    setting: { type: Object, required: true },
    todayAttendance: { type: Object, default: null },
    recentAttendances: { type: Array, required: true },
});

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

const minutesToTime = (value) => {
    const normalizedValue = ((value % 1440) + 1440) % 1440;
    const hour = String(Math.floor(normalizedValue / 60)).padStart(2, '0');
    const minute = String(normalizedValue % 60).padStart(2, '0');

    return `${hour}:${minute}`;
};

const isTimeWithinRange = (current, start, end) => {
    if (current === null || start === null || end === null) {
        return false;
    }

    if (start <= end) {
        return current >= start && current <= end;
    }

    return current >= start || current <= end;
};

const notify = useGlobalNotify();
const DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES = 20;
const DEFAULT_CHECK_IN_MAX_LATE_MINUTES = 40;
const officeReady = computed(() => props.setting.is_configured);
const activeLocationRadius = computed(() => `${props.setting.radius_meters ?? 100} m`);
const currentMakassarTime = ref(getMakassarTime());
const currentMakassarMinutes = computed(() => timeToMinutes(currentMakassarTime.value));
const checkInStartMinutes = computed(() => timeToMinutes(props.setting.check_in_time));
const checkInLateToleranceMinutes = computed(() => {
    const value = Number(props.setting.check_in_late_tolerance_minutes ?? DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES);

    return Number.isFinite(value) && value >= 0 ? value : DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES;
});
const checkInMaxLateMinutes = computed(() => {
    const value = Number(props.setting.check_in_max_late_minutes ?? DEFAULT_CHECK_IN_MAX_LATE_MINUTES);

    if (!Number.isFinite(value) || value < 0) {
        return DEFAULT_CHECK_IN_MAX_LATE_MINUTES;
    }

    return Math.max(value, checkInLateToleranceMinutes.value);
});
const checkInLateThresholdMinutes = computed(() => checkInStartMinutes.value === null ? null : checkInStartMinutes.value + checkInLateToleranceMinutes.value);
const checkInDeadlineMinutes = computed(() => checkInStartMinutes.value === null ? null : checkInStartMinutes.value + checkInMaxLateMinutes.value);
const checkInLateThresholdTime = computed(() => checkInLateThresholdMinutes.value === null ? '--:--' : minutesToTime(checkInLateThresholdMinutes.value));
const checkInDeadlineTime = computed(() => checkInDeadlineMinutes.value === null ? '--:--' : minutesToTime(checkInDeadlineMinutes.value));
const hasPendingClockIn = computed(() => !props.todayAttendance?.clock_in_at);
const hasReachedCheckInWindow = computed(() => isTimeWithinRange(currentMakassarMinutes.value, checkInStartMinutes.value, checkInDeadlineMinutes.value));
const isClockInLateWindow = computed(() => (
    hasPendingClockIn.value
    && hasReachedCheckInWindow.value
    && currentMakassarMinutes.value !== null
    && checkInLateThresholdMinutes.value !== null
    && currentMakassarMinutes.value > checkInLateThresholdMinutes.value
));
const hasPendingClockOut = computed(() => Boolean(props.todayAttendance?.clock_in_at) && !props.todayAttendance?.clock_out_at);
const hasReachedCheckOutTime = computed(() => Boolean(props.setting.check_out_time) && currentMakassarTime.value >= props.setting.check_out_time);
const canClockIn = computed(() => hasPendingClockIn.value && hasReachedCheckInWindow.value);
const canClockOut = computed(() => hasPendingClockOut.value && hasReachedCheckOutTime.value);
const checkInAvailabilityHint = computed(() => {
    if (!hasPendingClockIn.value) {
        return '';
    }

    if (!props.setting.check_in_time) {
        return 'Admin belum mengatur jam masuk.';
    }

    if (!hasReachedCheckInWindow.value) {
        if (currentMakassarMinutes.value !== null && checkInStartMinutes.value !== null && currentMakassarMinutes.value < checkInStartMinutes.value) {
            return `Absen masuk dibuka mulai ${props.setting.check_in_time} WITA.`;
        }

        return `Absen masuk ditutup pukul ${checkInDeadlineTime.value} WITA.`;
    }

    return '';
});
const checkInLateHint = computed(() => (
    isClockInLateWindow.value
        ? `Absen masuk tetap tersedia, tetapi akan tercatat Lambat karena melewati ${checkInLateThresholdTime.value} WITA.`
        : ''
));
const checkOutAvailabilityHint = computed(() => {
    if (!hasPendingClockOut.value) {
        return '';
    }

    if (!props.setting.check_out_time) {
        return 'Admin belum mengatur jam pulang.';
    }

    if (!hasReachedCheckOutTime.value) {
        return `Absen pulang dibuka mulai ${props.setting.check_out_time} WITA.`;
    }

    return '';
});
let currentTimeIntervalId = null;

const {
    activeAction,
    cameraError,
    cameraLoading,
    cameraReady,
    currentPosition,
    ensureLocation,
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
        label: 'Absen Masuk',
        value: formatTime(props.todayAttendance?.clock_in_at),
    },
    {
        label: 'Absen Pulang',
        value: formatTime(props.todayAttendance?.clock_out_at),
    },
    {
        label: 'Lokasi Validasi',
        value: officeReady.value ? 'Kantor' : 'Nonaktif',
    },
]);

const handleClockIn = () => {
    if (!canClockIn.value) {
        notify.error(checkInAvailabilityHint.value || 'Absen masuk belum tersedia.');
        return;
    }

    submitPresence({
        actionKey: 'clock-in',
        routeName: 'employee.attendance.clock-in',
        successMessage: 'Absen masuk berhasil disimpan.',
    });
};

const handleClockOut = () => {
    if (!canClockOut.value) {
        notify.error(checkOutAvailabilityHint.value || 'Absen pulang belum tersedia.');
        return;
    }

    submitPresence({
        actionKey: 'clock-out',
        routeName: 'employee.attendance.clock-out',
        successMessage: 'Absen pulang berhasil disimpan.',
    });
};

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
    <Head title="Presensi" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Presensi</h2>

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

            <section class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ruang Presensi</h3>
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

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canClockIn || activeAction === 'clock-out' || !officeReady"
                            @click="handleClockIn"
                        >
                            {{ activeAction === 'clock-in' ? 'Memproses Absen Masuk...' : 'Absen Masuk' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canClockOut || activeAction === 'clock-in' || !officeReady"
                            @click="handleClockOut"
                        >
                            {{ activeAction === 'clock-out' ? 'Memproses Absen Pulang...' : 'Absen Pulang' }}
                        </button>
                    </div>

                    <div v-if="cameraError || locationError || !officeReady || checkInAvailabilityHint || checkInLateHint || checkOutAvailabilityHint" class="mt-3 space-y-2">
                        <p v-if="cameraError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                            {{ cameraError }}
                        </p>
                        <p v-if="locationError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                            {{ locationError }}
                        </p>
                        <p v-if="checkInAvailabilityHint" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            {{ checkInAvailabilityHint }}
                        </p>
                        <p v-if="checkInLateHint" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            {{ checkInLateHint }}
                        </p>
                        <p v-if="checkOutAvailabilityHint" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            {{ checkOutAvailabilityHint }}
                        </p>
                        <p v-if="!officeReady" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            Admin belum mengisi lokasi kantor.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Status Hari Ini</h3>

                    <div class="mt-3 grid gap-3">
                        <div class="rounded-lg border border-slate-200 px-3 py-3 text-sm dark:border-slate-700">
                            <p class="text-slate-500 dark:text-slate-400">Selanjutnya</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                                {{ hasPendingClockIn ? (canClockIn ? (isClockInLateWindow ? 'Masuk Lambat' : 'Masuk') : 'Menunggu Jam Masuk') : hasPendingClockOut ? (canClockOut ? 'Pulang' : 'Menunggu Jam Pulang') : 'Selesai' }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Jadwal kerja:
                            <span class="font-semibold">{{ props.setting.check_in_time ?? '--:--' }} - {{ props.setting.check_out_time ?? '--:--' }}</span>
                        </div>

                        <div class="rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Batas absen masuk normal:
                            <span class="font-semibold">{{ props.setting.check_in_time ?? '--:--' }} - {{ checkInLateThresholdTime }} WITA</span>
                        </div>

                        <div class="rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Batas maksimal terlambat:
                            <span class="font-semibold">{{ checkInDeadlineTime }} WITA</span>
                        </div>

                        <div class="rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Jam sekarang:
                            <span class="font-semibold">{{ currentMakassarTime }} WITA</span>
                        </div>

                        <div class="rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Validasi radius:
                            <span class="font-semibold">Kantor - {{ activeLocationRadius }}</span>
                        </div>

                        <div class="rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Kamera:
                            <span class="font-semibold">{{ cameraReady ? 'Aktif' : 'Belum aktif' }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Riwayat Presensi Terakhir</h2>
                <div class="mt-3 overflow-x-auto">
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
        </div>
    </AuthenticatedLayout>
</template>
