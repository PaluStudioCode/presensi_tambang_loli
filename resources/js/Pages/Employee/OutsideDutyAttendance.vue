<script setup>
import Modal from '@/Components/Modal.vue';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import { usePresenceCapture } from '@/composables/usePresenceCapture';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    approvedTodayOutsideDuties: {
        type: Array,
        required: true,
    },
    selectedOutsideDutyId: {
        type: Number,
        default: null,
    },
});

const notify = useGlobalNotify();
const alwaysReady = computed(() => true);
const showPhotoModal = ref(false);
const selectedPhotoSrc = ref('');
const selectedPhotoTitle = ref('');
const selectedPhotoMeta = ref('');
const nowTick = ref(new Date());
let clockIntervalId = null;

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
    officeReady: alwaysReady,
    unavailableMessage: 'Tidak ada tugas luar yang siap diabsen.',
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

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const mapsUrl = (outsideDuty) => `https://www.google.com/maps?q=${outsideDuty.latitude},${outsideDuty.longitude}`;

const currentTimeIso = computed(() => new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    timeZone: 'Asia/Makassar',
}).format(nowTick.value));

const normalizeTime = (value) => {
    if (!value) {
        return '';
    }

    const [hour = '00', minute = '00', second = '00'] = value.split(':');

    return `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}:${second.padStart(2, '0')}`;
};

const isOutsideDutyTimeOpen = (outsideDuty) => (
    normalizeTime(outsideDuty.planned_start) <= currentTimeIso.value
    && currentTimeIso.value <= normalizeTime(outsideDuty.planned_end)
);

const attendanceAvailabilityLabel = (outsideDuty) => {
    if (outsideDuty.attended_at) {
        return 'Sudah melakukan absen tugas luar.';
    }

    if (currentTimeIso.value < normalizeTime(outsideDuty.planned_start)) {
        return `Absen tugas luar dibuka pukul ${formatTime(outsideDuty.planned_start)} WITA.`;
    }

    if (currentTimeIso.value > normalizeTime(outsideDuty.planned_end)) {
        return `Absen tugas luar ditutup pukul ${formatTime(outsideDuty.planned_end)} WITA.`;
    }

    return `Absen tersedia sampai pukul ${formatTime(outsideDuty.planned_end)} WITA.`;
};

const attendanceStatusLabel = (outsideDuty) => {
    if (outsideDuty.attended_at) {
        return 'Sudah Absen';
    }

    if (!isOutsideDutyTimeOpen(outsideDuty)) {
        return currentTimeIso.value < normalizeTime(outsideDuty.planned_start) ? 'Belum Dibuka' : 'Ditutup';
    }

    return isSelectedOutsideDuty(outsideDuty) ? 'Dipilih' : 'Siap Absen';
};

const attendanceStatusClass = (outsideDuty) => {
    if (outsideDuty.attended_at || isOutsideDutyTimeOpen(outsideDuty)) {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (currentTimeIso.value > normalizeTime(outsideDuty.planned_end)) {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
};

const canAttendOutsideDuty = (outsideDuty) => (
    outsideDuty.approval_status === 'Approved'
    && !outsideDuty.attended_at
    && isOutsideDutyTimeOpen(outsideDuty)
);

const isSelectedOutsideDuty = (outsideDuty) => outsideDuty.id === props.selectedOutsideDutyId;

const handleAttendOutsideDuty = (outsideDuty) => {
    if (!canAttendOutsideDuty(outsideDuty)) {
        notify.error(attendanceAvailabilityLabel(outsideDuty));
        return;
    }

    submitPresence({
        actionKey: `outside-duty-attend-${outsideDuty.id}`,
        routeName: 'employee.outside-duties.attend',
        routeParams: outsideDuty.id,
        successMessage: 'Absen tugas luar berhasil disimpan.',
    });
};

const openPhotoModal = (outsideDuty) => {
    if (!outsideDuty.attendance_photo) {
        return;
    }

    selectedPhotoSrc.value = outsideDuty.attendance_photo;
    selectedPhotoTitle.value = 'Foto Absen Tugas Luar';
    selectedPhotoMeta.value = `${formatDate(outsideDuty.duty_date)} - ${outsideDuty.location_name}`;
    showPhotoModal.value = true;
};

const closePhotoModal = () => {
    showPhotoModal.value = false;
    selectedPhotoSrc.value = '';
    selectedPhotoTitle.value = '';
    selectedPhotoMeta.value = '';
};

onBeforeUnmount(() => {
    stopCamera();

    if (clockIntervalId) {
        window.clearInterval(clockIntervalId);
    }
});

onMounted(() => {
    clockIntervalId = window.setInterval(() => {
        nowTick.value = new Date();
    }, 30000);
});
</script>

<template>
    <Head title="Absen Tugas Luar" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Absen Tugas Luar</h2>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('employee.outside-duties.create')"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Ajukan
                        </Link>
                        <Link
                            :href="route('employee.outside-duties.index')"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Riwayat
                        </Link>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Kamera dan GPS</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Tugas luar yang dipilih</h3>
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

                <div class="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(320px,1.05fr)]">
                    <div class="grid items-start gap-3 md:grid-cols-[minmax(0,1fr)_18rem] xl:grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_18rem]">
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
                            <p class="mt-1 break-all font-semibold text-slate-900 dark:text-slate-100">
                                {{ currentPosition ? `${currentPosition.latitude.toFixed(6)}, ${currentPosition.longitude.toFixed(6)}` : 'Belum diambil' }}
                            </p>
                            <p class="mt-1 text-slate-500 dark:text-slate-400">
                                Akurasi: {{ currentPosition?.accuracy ? `${currentPosition.accuracy} m` : '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <article
                            v-for="outsideDuty in approvedTodayOutsideDuties"
                            :key="outsideDuty.id"
                            class="rounded-lg border bg-slate-50 p-4 dark:bg-slate-800/60"
                            :class="isSelectedOutsideDuty(outsideDuty) ? 'border-emerald-300 ring-2 ring-emerald-100 dark:border-emerald-500/50 dark:ring-emerald-500/10' : 'border-slate-200 dark:border-slate-700'"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ outsideDuty.location_name }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ formatTime(outsideDuty.planned_start) }} - {{ formatTime(outsideDuty.planned_end) }}</p>
                                    <a :href="mapsUrl(outsideDuty)" target="_blank" rel="noreferrer" class="mt-1 inline-flex break-all text-xs font-medium text-sky-700 hover:text-sky-800 dark:text-sky-300">
                                        {{ outsideDuty.latitude }}, {{ outsideDuty.longitude }}
                                    </a>
                                </div>

                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="attendanceStatusClass(outsideDuty)"
                                >
                                    {{ attendanceStatusLabel(outsideDuty) }}
                                </span>
                            </div>

                            <div class="mt-3 grid gap-2 text-sm text-slate-600 dark:text-slate-300 sm:grid-cols-2">
                                <p>Radius final: <span class="font-semibold">{{ outsideDuty.approved_radius_meters ?? '-' }} m</span></p>
                                <p>Absen: <span class="font-semibold">{{ formatDateTime(outsideDuty.attended_at) }}</span></p>
                            </div>

                            <p
                                v-if="outsideDuty.approval_status === 'Approved' && !outsideDuty.attended_at"
                                class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ attendanceAvailabilityLabel(outsideDuty) }}
                            </p>

                            <p v-if="outsideDuty.attendance_location" class="mt-2 break-all text-sm text-slate-500 dark:text-slate-400">
                                Lokasi absen: {{ outsideDuty.attendance_location }}
                            </p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="!canAttendOutsideDuty(outsideDuty) || activeAction !== ''"
                                    @click="handleAttendOutsideDuty(outsideDuty)"
                                >
                                    {{ activeAction === 'outside-duty-attend-' + outsideDuty.id ? 'Memproses...' : 'Absen di Titik Tugas' }}
                                </button>
                                <button
                                    v-if="outsideDuty.attendance_photo"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                    @click="openPhotoModal(outsideDuty)"
                                >
                                    Lihat Foto Absen
                                </button>
                            </div>
                        </article>

                        <div v-if="!approvedTodayOutsideDuties.length" class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Pilih tugas luar yang sudah disetujui dari halaman riwayat.
                        </div>
                    </div>
                </div>

                <div v-if="cameraError || locationError" class="mt-3 space-y-2">
                    <p v-if="cameraError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ cameraError }}
                    </p>
                    <p v-if="locationError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ locationError }}
                    </p>
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
                        >
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
