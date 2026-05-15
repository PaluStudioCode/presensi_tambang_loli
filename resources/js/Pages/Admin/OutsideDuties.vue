<script setup>
import Modal from '@/Components/Modal.vue';
import { useGlobalConfirm } from '@/composables/useGlobalConfirm';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, reactive, ref, watch } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    employees: {
        type: Array,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    outsideDuties: {
        type: Object,
        required: true,
    },
});

const confirm = useGlobalConfirm();
const notify = useGlobalNotify();
const actionLoadingId = ref(null);
const actionLoadingType = ref(null);
const showPhotoModal = ref(false);
const showApprovalModal = ref(false);
const selectedPhotoSrc = ref('');
const selectedPhotoTitle = ref('');
const selectedPhotoMeta = ref('');
const selectedOutsideDuty = ref(null);
let filterDebounceTimeoutId = null;

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    status: props.filters.status ?? 'all',
    employee_id: props.filters.employee_id ?? '',
});

const approvalForm = useForm({
    approved_radius_meters: '',
});

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

const formatDate = (value) => {
    if (!value) return '-';

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
    if (!value) return '-';

    return value.slice(0, 5);
};

const formatDateTime = (value) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const mapsUrl = (outsideDuty) => `https://www.google.com/maps?q=${outsideDuty.latitude},${outsideDuty.longitude}`;

const coordinateMapsUrl = (coordinates) => {
    if (!coordinates) return '';

    return `https://www.google.com/maps?q=${encodeURIComponent(String(coordinates).replace(/\s+/g, ''))}`;
};

const applyFilter = () => {
    router.get(route('admin.outside-duties.index'), {
        date_from: form.date_from,
        date_to: form.date_to,
        status: form.status,
        employee_id: form.employee_id || null,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilter = () => {
    form.status = 'all';
    form.employee_id = '';
};

const openPhotoModal = (outsideDuty, type = 'request') => {
    const photoSrc = type === 'attendance' ? outsideDuty.attendance_photo : outsideDuty.request_photo;

    if (!photoSrc) {
        return;
    }

    selectedPhotoSrc.value = photoSrc;
    selectedPhotoTitle.value = type === 'attendance' ? 'Foto Absen Tugas Luar' : 'Bukti Pengajuan Tugas Luar';
    selectedPhotoMeta.value = `${outsideDuty.employee_name} - ${formatDate(outsideDuty.duty_date)}`;
    showPhotoModal.value = true;
};

const closePhotoModal = () => {
    showPhotoModal.value = false;
    selectedPhotoSrc.value = '';
    selectedPhotoTitle.value = '';
    selectedPhotoMeta.value = '';
};

const openApprovalModal = (outsideDuty) => {
    selectedOutsideDuty.value = outsideDuty;
    approvalForm.clearErrors();
    approvalForm.approved_radius_meters = outsideDuty.approved_radius_meters ?? outsideDuty.requested_radius_meters;
    showApprovalModal.value = true;
};

const closeApprovalModal = () => {
    if (approvalForm.processing) {
        return;
    }

    showApprovalModal.value = false;
    selectedOutsideDuty.value = null;
    approvalForm.reset();
};

const submitApproval = () => {
    if (!selectedOutsideDuty.value) {
        return;
    }

    actionLoadingId.value = selectedOutsideDuty.value.id;
    actionLoadingType.value = 'approve';

    approvalForm.patch(route('admin.outside-duties.approve', selectedOutsideDuty.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            notify.success('Pengajuan tugas luar berhasil disetujui.');
            showApprovalModal.value = false;
            selectedOutsideDuty.value = null;
            approvalForm.reset();
        },
        onError: () => {
            notify.error('Gagal menyetujui pengajuan tugas luar.');
        },
        onFinish: () => {
            actionLoadingId.value = null;
            actionLoadingType.value = null;
        },
    });
};

const rejectOutsideDuty = async (outsideDuty) => {
    const shouldReject = await confirm({
        title: 'Tolak Pengajuan Tugas Luar?',
        message: 'Pengajuan ini akan ditandai sebagai ditolak dan tidak dapat digunakan untuk absen tugas luar.',
        confirmText: 'Ya, Tolak',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!shouldReject) {
        return;
    }

    actionLoadingId.value = outsideDuty.id;
    actionLoadingType.value = 'reject';

    router.patch(route('admin.outside-duties.reject', outsideDuty.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            notify.success('Pengajuan tugas luar berhasil ditolak.');
        },
        onError: () => {
            notify.error('Gagal menolak pengajuan tugas luar.');
        },
        onFinish: () => {
            actionLoadingId.value = null;
            actionLoadingType.value = null;
        },
    });
};

const visitPage = (url) => {
    if (!url) return;

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};

watch(
    () => [form.date_from, form.date_to, form.status, form.employee_id],
    () => {
        if (filterDebounceTimeoutId) {
            clearTimeout(filterDebounceTimeoutId);
        }

        filterDebounceTimeoutId = setTimeout(() => {
            applyFilter();
        }, 300);
    },
);

onBeforeUnmount(() => {
    if (filterDebounceTimeoutId) {
        clearTimeout(filterDebounceTimeoutId);
    }
});
</script>

<template>
    <Head title="Monitoring Tugas Luar" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-gray-500">Total Pengajuan</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.totalRequests }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-gray-500">Menunggu</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.pendingRequests }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-gray-500">Disetujui</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.approvedRequests }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs text-gray-500">Ditolak</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.rejectedRequests }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <div>
                        <label class="block text-xs text-gray-500">Dari Tanggal</label>
                        <input v-model="form.date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Sampai Tanggal</label>
                        <input v-model="form.date_to" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Status</label>
                        <select v-model="form.status" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <option value="all">Semua</option>
                            <option value="Pending">Menunggu</option>
                            <option value="Approved">Disetujui</option>
                            <option value="Rejected">Ditolak</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Karyawan</label>
                        <select v-model="form.employee_id" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <option value="">Semua Karyawan</option>
                            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                {{ employee.full_name }} ({{ employee.id_number }})
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="resetFilter">
                            Atur Ulang
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-gray-100 text-left text-gray-500">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Tanggal</th>
                                <th class="py-2 pe-4 font-medium">Karyawan</th>
                                <th class="py-2 pe-4 font-medium">Lokasi</th>
                                <th class="py-2 pe-4 font-medium">Radius</th>
                                <th class="py-2 pe-4 font-medium">Bukti</th>
                                <th class="py-2 pe-4 font-medium">Status</th>
                                <th class="py-2 pe-4 font-medium">Absen Tugas</th>
                                <th class="py-2 pe-4 font-medium">Disetujui Oleh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-for="outsideDuty in outsideDuties.data" :key="outsideDuty.id">
                                <td class="py-3 pe-4 whitespace-nowrap">
                                    <p>{{ formatDate(outsideDuty.duty_date) }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ formatTime(outsideDuty.planned_start) }} - {{ formatTime(outsideDuty.planned_end) }}</p>
                                </td>
                                <td class="py-3 pe-4">
                                    <p class="font-medium text-gray-900">{{ outsideDuty.employee_name }}</p>
                                    <p class="text-xs text-gray-500">{{ outsideDuty.id_number ?? '-' }}</p>
                                    <p class="mt-1 max-w-[260px] truncate text-xs text-gray-500" :title="outsideDuty.reason">{{ outsideDuty.reason ?? '-' }}</p>
                                </td>
                                <td class="py-3 pe-4">
                                    <p class="font-medium text-gray-900">{{ outsideDuty.location_name }}</p>
                                    <a :href="mapsUrl(outsideDuty)" target="_blank" rel="noreferrer" class="mt-1 inline-flex text-xs font-medium text-sky-700 hover:text-sky-800">
                                        {{ outsideDuty.latitude }}, {{ outsideDuty.longitude }}
                                    </a>
                                </td>
                                <td class="py-3 pe-4 whitespace-nowrap">
                                    <p>Diajukan: {{ outsideDuty.requested_radius_meters }} m</p>
                                    <p class="mt-1 text-xs text-gray-500">Final: {{ outsideDuty.approved_radius_meters ?? '-' }} m</p>
                                </td>
                                <td class="py-3 pe-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        @click="openPhotoModal(outsideDuty)"
                                    >
                                        Bukti Pengajuan
                                    </button>
                                </td>
                                <td class="py-3 pe-4">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(outsideDuty.approval_status)">
                                        {{ statusLabel(outsideDuty.approval_status) }}
                                    </span>
                                    <div v-if="outsideDuty.approval_status === 'Pending'" class="mt-2 flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                            :disabled="actionLoadingId === outsideDuty.id"
                                            @click="openApprovalModal(outsideDuty)"
                                        >
                                            <span v-if="actionLoadingId === outsideDuty.id && actionLoadingType === 'approve'">...</span>
                                            <span v-else>Setujui</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60 dark:bg-rose-600 dark:hover:bg-rose-500"
                                            :disabled="actionLoadingId === outsideDuty.id"
                                            @click="rejectOutsideDuty(outsideDuty)"
                                        >
                                            <span v-if="actionLoadingId === outsideDuty.id && actionLoadingType === 'reject'">...</span>
                                            <span v-else>Tolak</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3 pe-4">
                                    <p class="whitespace-nowrap">{{ formatDateTime(outsideDuty.attended_at) }}</p>
                                    <a
                                        v-if="outsideDuty.attendance_location"
                                        :href="coordinateMapsUrl(outsideDuty.attendance_location)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 block max-w-[220px] truncate text-xs font-medium text-sky-700 hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                                        :title="outsideDuty.attendance_location"
                                    >
                                        {{ outsideDuty.attendance_location }}
                                    </a>
                                    <button
                                        v-if="outsideDuty.attendance_photo"
                                        type="button"
                                        class="mt-2 inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        @click="openPhotoModal(outsideDuty, 'attendance')"
                                    >
                                        Lihat Foto Absen
                                    </button>
                                </td>
                                <td class="py-3 pe-4">{{ outsideDuty.approved_by ?? '-' }}</td>
                            </tr>
                            <tr v-if="outsideDuties.data.length === 0">
                                <td colspan="8" class="py-4 text-center text-gray-500">Data tugas luar tidak ditemukan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="outsideDuties.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="(link, index) in outsideDuties.links"
                        :key="index"
                        :disabled="!link.url || link.active"
                        class="rounded-md border px-3 py-1.5 text-sm disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
                        :class="link.active ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900' : 'border-gray-300 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'"
                        @click="visitPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>

            <Modal :show="showApprovalModal" max-width="lg" @close="closeApprovalModal">
                <form class="p-4 sm:p-6" @submit.prevent="submitApproval">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Setujui Tugas Luar</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                                {{ selectedOutsideDuty?.employee_name }} - {{ selectedOutsideDuty?.location_name }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="closeApprovalModal"
                        >
                            Tutup
                        </button>
                    </div>

                    <div class="mt-4">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Radius Final (meter)</label>
                        <input
                            v-model="approvalForm.approved_radius_meters"
                            type="number"
                            min="1"
                            max="50000"
                            class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                            required
                        >
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Radius diajukan: {{ selectedOutsideDuty?.requested_radius_meters ?? '-' }} meter.
                        </p>
                        <p v-if="approvalForm.errors.approved_radius_meters" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ approvalForm.errors.approved_radius_meters }}</p>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="closeApprovalModal"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="approvalForm.processing"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                        >
                            {{ approvalForm.processing ? 'Menyetujui...' : 'Setujui' }}
                        </button>
                    </div>
                </form>
            </Modal>

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
