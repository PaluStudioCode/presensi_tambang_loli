<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    outsideDuties: {
        type: Object,
        required: true,
    },
});

const showPhotoModal = ref(false);
const selectedPhotoSrc = ref('');
const selectedPhotoTitle = ref('');
const selectedPhotoMeta = ref('');
const nowTick = ref(new Date());
let clockIntervalId = null;

const todayIso = computed(() => nowTick.value.toLocaleDateString('en-CA', { timeZone: 'Asia/Makassar' }));
const currentTimeIso = computed(() => new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    timeZone: 'Asia/Makassar',
}).format(nowTick.value));

const summaryCards = computed(() => [
    {
        label: 'Total Pengajuan',
        value: props.outsideDuties.total ?? props.outsideDuties.data.length,
    },
    {
        label: 'Menunggu',
        value: props.outsideDuties.data.filter((outsideDuty) => outsideDuty.approval_status === 'Pending').length,
    },
    {
        label: 'Disetujui',
        value: props.outsideDuties.data.filter((outsideDuty) => outsideDuty.approval_status === 'Approved').length,
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

const canStartAttendance = (outsideDuty) => (
    outsideDuty.approval_status === 'Approved'
    && !outsideDuty.attended_at
    && outsideDuty.duty_date === todayIso.value
    && isOutsideDutyTimeOpen(outsideDuty)
);

const attendanceActionLabel = (outsideDuty) => {
    if (outsideDuty.attended_at) {
        return 'Sudah Absen';
    }

    if (outsideDuty.approval_status !== 'Approved') {
        return '-';
    }

    if (outsideDuty.duty_date > todayIso.value) {
        return 'Menunggu Tanggal';
    }

    if (outsideDuty.duty_date < todayIso.value) {
        return 'Tidak Ada Absen';
    }

    if (currentTimeIso.value < normalizeTime(outsideDuty.planned_start)) {
        return `Dibuka pukul ${formatTime(outsideDuty.planned_start)} WITA`;
    }

    if (currentTimeIso.value > normalizeTime(outsideDuty.planned_end)) {
        return `Ditutup pukul ${formatTime(outsideDuty.planned_end)} WITA`;
    }

    return 'Siap Absen';
};

const openPhotoModal = (outsideDuty, type = 'request') => {
    const photoSrc = type === 'attendance' ? outsideDuty.attendance_photo : outsideDuty.request_photo;

    if (!photoSrc) {
        return;
    }

    selectedPhotoSrc.value = photoSrc;
    selectedPhotoTitle.value = type === 'attendance' ? 'Foto Absen Tugas Luar' : 'Bukti Pengajuan Tugas Luar';
    selectedPhotoMeta.value = `${formatDate(outsideDuty.duty_date)} - ${outsideDuty.location_name}`;
    showPhotoModal.value = true;
};

const closePhotoModal = () => {
    showPhotoModal.value = false;
    selectedPhotoSrc.value = '';
    selectedPhotoTitle.value = '';
    selectedPhotoMeta.value = '';
};

const visitPage = (url) => {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};

onMounted(() => {
    clockIntervalId = window.setInterval(() => {
        nowTick.value = new Date();
    }, 30000);
});

onBeforeUnmount(() => {
    if (clockIntervalId) {
        window.clearInterval(clockIntervalId);
    }
});
</script>

<template>
    <Head title="Riwayat Tugas Luar" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Riwayat Tugas Luar</h2>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('employee.outside-duties.create')"
                            class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            Ajukan Tugas Luar
                        </Link>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
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

            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pe-3 font-medium">Tanggal</th>
                                <th class="py-2 pe-3 font-medium">Lokasi</th>
                                <th class="py-2 pe-3 font-medium">Radius</th>
                                <th class="py-2 pe-3 font-medium">Status</th>
                                <th class="py-2 pe-3 font-medium">Absen</th>
                                <th class="py-2 pe-3 font-medium">Bukti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                            <tr v-for="outsideDuty in outsideDuties.data" :key="outsideDuty.id">
                                <td class="py-3 pe-3 whitespace-nowrap">
                                    <p>{{ formatDate(outsideDuty.duty_date) }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ formatTime(outsideDuty.planned_start) }} - {{ formatTime(outsideDuty.planned_end) }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ formatDateTime(outsideDuty.created_at) }}</p>
                                </td>
                                <td class="py-3 pe-3">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ outsideDuty.location_name }}</p>
                                    <a :href="mapsUrl(outsideDuty)" target="_blank" rel="noreferrer" class="mt-1 inline-flex text-xs font-medium text-sky-700 hover:text-sky-800 dark:text-sky-300">
                                        {{ outsideDuty.latitude }}, {{ outsideDuty.longitude }}
                                    </a>
                                    <p class="mt-1 max-w-[260px] truncate text-xs text-slate-500 dark:text-slate-400" :title="outsideDuty.reason">{{ outsideDuty.reason }}</p>
                                </td>
                                <td class="py-3 pe-3 whitespace-nowrap">
                                    <p>Diajukan: {{ outsideDuty.requested_radius_meters }} m</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Disetujui: {{ outsideDuty.approved_radius_meters ?? '-' }} m</p>
                                </td>
                                <td class="py-3 pe-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(outsideDuty.approval_status)">
                                        {{ statusLabel(outsideDuty.approval_status) }}
                                    </span>
                                    <p v-if="outsideDuty.approved_by" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Oleh {{ outsideDuty.approved_by }}</p>
                                </td>
                                <td class="py-3 pe-3">
                                    <p class="whitespace-nowrap">{{ formatDateTime(outsideDuty.attended_at) }}</p>
                                    <p v-if="outsideDuty.attendance_location" class="mt-1 max-w-[220px] truncate text-xs text-slate-500 dark:text-slate-400" :title="outsideDuty.attendance_location">
                                        {{ outsideDuty.attendance_location }}
                                    </p>
                                    <Link
                                        v-if="canStartAttendance(outsideDuty)"
                                        :href="route('employee.outside-duties.attendance', { outside_duty_id: outsideDuty.id })"
                                        class="mt-2 inline-flex items-center justify-center rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700"
                                    >
                                        Absen
                                    </Link>
                                    <button
                                        v-if="outsideDuty.attendance_photo"
                                        type="button"
                                        class="mt-2 inline-flex items-center justify-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        @click="openPhotoModal(outsideDuty, 'attendance')"
                                    >
                                        Lihat Foto Absen
                                    </button>
                                    <p v-if="outsideDuty.approval_status === 'Approved' && !canStartAttendance(outsideDuty) && !outsideDuty.attendance_photo" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        {{ attendanceActionLabel(outsideDuty) }}
                                    </p>
                                </td>
                                <td class="py-3 pe-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        @click="openPhotoModal(outsideDuty)"
                                    >
                                        Bukti Pengajuan
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!outsideDuties.data.length">
                                <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada pengajuan tugas luar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="outsideDuties.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="(link, index) in outsideDuties.links"
                        :key="index"
                        type="button"
                        :disabled="!link.url || link.active"
                        class="rounded-lg border px-3 py-1.5 text-sm disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
                        :class="link.active ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900' : 'border-gray-300 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'"
                        @click="visitPage(link.url)"
                        v-html="link.label"
                    />
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
