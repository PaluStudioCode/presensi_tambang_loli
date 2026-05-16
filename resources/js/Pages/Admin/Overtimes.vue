<script setup>
import Modal from '@/Components/Modal.vue';
import { useGlobalConfirm } from '@/composables/useGlobalConfirm';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
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
    overtimes: {
        type: Object,
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

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    status: props.filters.status ?? 'all',
    employee_id: props.filters.employee_id ?? '',
});

let filterDebounceTimeoutId = null;

const statusClass = (status) => {
    if (status === 'Approved') return 'bg-green-100 text-green-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    if (status === 'Rejected') return 'bg-red-100 text-red-700 dark:bg-rose-500/10 dark:text-rose-300';
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

const applyFilter = () => {
    router.get(route('admin.overtimes.index'), {
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

const openPhotoModal = (overtime, type) => {
    const src = {
        request: overtime.overtime_request_photo,
        start: overtime.overtime_start_photo,
        end: overtime.overtime_end_photo,
    }[type];

    if (!src) {
        return;
    }

    const title = {
        request: 'Foto Pengajuan Lembur',
        start: 'Foto Mulai Lembur',
        end: 'Foto Selesai Lembur',
    }[type];

    selectedPhotoSrc.value = src;
    selectedPhotoTitle.value = title;
    selectedPhotoMeta.value = `${overtime.employee_name} - ${formatDate(overtime.overtime_date)}`;
    showPhotoModal.value = true;
};

const closePhotoModal = () => {
    showPhotoModal.value = false;
    selectedPhotoSrc.value = '';
    selectedPhotoTitle.value = '';
    selectedPhotoMeta.value = '';
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
    <Head title="Monitoring dan Persetujuan Lembur" />

    <AuthenticatedLayout>
        <div class="space-y-4">
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Total Pengajuan</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.totalRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Menunggu</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.pendingRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Disetujui</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.approvedRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Ditolak</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.rejectedRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Jam Disetujui</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.approvedHours }} jam</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl p-4">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">Dari Tanggal</label>
                            <input v-model="form.date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Sampai Tanggal</label>
                            <input v-model="form.date_to" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" />
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

                <div class="bg-white border border-gray-100 rounded-xl p-4">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500 border-b border-gray-100">
                                <tr>
                                    <th class="py-2 pe-4 font-medium">Tanggal</th>
                                    <th class="py-2 pe-4 font-medium">Karyawan</th>
                                    <th class="py-2 pe-4 font-medium">Rencana</th>
                                    <th class="py-2 pe-4 font-medium">Aktual</th>
                                    <th class="py-2 pe-4 font-medium">Foto</th>
                                    <th class="py-2 pe-4 font-medium">Status</th>
                                    <th class="py-2 pe-4 font-medium">Disetujui Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr v-for="overtime in overtimes.data" :key="overtime.id">
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="font-medium text-gray-900">{{ overtime.employee_name }}</p>
                                        <p class="text-xs text-gray-500">{{ overtime.id_number ?? '-' }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ overtime.reason ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}</td>
                                    <td class="py-3 pe-4">
                                        <div class="flex flex-col gap-2">
                                            <button
                                                v-if="overtime.overtime_request_photo"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                @click="openPhotoModal(overtime, 'request')"
                                            >
                                                Lihat Foto Pengajuan
                                            </button>
                                            <button
                                                v-if="overtime.overtime_start_photo"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                @click="openPhotoModal(overtime, 'start')"
                                            >
                                                Lihat Foto Mulai
                                            </button>
                                            <button
                                                v-if="overtime.overtime_end_photo"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                @click="openPhotoModal(overtime, 'end')"
                                            >
                                                Lihat Foto Selesai
                                            </button>
                                            <span v-if="!overtime.overtime_request_photo && !overtime.overtime_start_photo && !overtime.overtime_end_photo" class="text-xs text-gray-500">-</span>
                                        </div>
                                    </td>
                                    <td class="py-3 pe-4">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(overtime.approval_status)">
                                            {{ statusLabel(overtime.approval_status) }}
                                        </span>
                                        <div v-if="overtime.approval_status === 'Pending'" class="mt-2 flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                                :disabled="actionLoadingId === overtime.id"
                                                @click="processOvertime(overtime.id, 'approve')"
                                            >
                                                <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'approve'">...</span>
                                                <span v-else>Setujui</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60 dark:bg-rose-600 dark:hover:bg-rose-500"
                                                :disabled="actionLoadingId === overtime.id"
                                                @click="processOvertime(overtime.id, 'reject')"
                                            >
                                                <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'reject'">...</span>
                                                <span v-else>Tolak</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-3 pe-4">{{ overtime.approved_by ?? '-' }}</td>
                                </tr>
                                <tr v-if="overtimes.data.length === 0">
                                    <td colspan="7" class="py-4 text-center text-gray-500">Data lembur tidak ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="overtimes.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="(link, index) in overtimes.links"
                            :key="index"
                            :disabled="!link.url || link.active"
                            class="rounded-md border px-3 py-1.5 text-sm disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
                            :class="link.active ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900' : 'border-gray-300 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'"
                            @click="router.visit(link.url, { preserveScroll: true, preserveState: true })"
                            v-html="link.label"
                        />
                    </div>
                </div>

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
