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
    leaveRequests: {
        type: Object,
        required: true,
    },
});

const confirm = useGlobalConfirm();
const notify = useGlobalNotify();
const actionLoadingId = ref(null);
const actionLoadingType = ref(null);
const showProofModal = ref(false);
const selectedProofSrc = ref('');
const selectedProofDownloadUrl = ref('');
const selectedProofTitle = ref('');
const selectedProofMeta = ref('');
let filterDebounceTimeoutId = null;

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    status: props.filters.status ?? 'all',
    employee_id: props.filters.employee_id ?? '',
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

const applyFilter = () => {
    router.get(route('admin.leaves.index'), {
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

const openProofModal = (leaveRequest) => {
    if (!leaveRequest.proof_photo) {
        return;
    }

    selectedProofSrc.value = leaveRequest.proof_photo;
    selectedProofDownloadUrl.value = leaveRequest.proof_photo_download_url;
    selectedProofTitle.value = `Bukti ${leaveRequest.leave_type}`;
    selectedProofMeta.value = `${leaveRequest.employee_name} - ${formatDate(leaveRequest.leave_date)}`;
    showProofModal.value = true;
};

const closeProofModal = () => {
    showProofModal.value = false;
    selectedProofSrc.value = '';
    selectedProofDownloadUrl.value = '';
    selectedProofTitle.value = '';
    selectedProofMeta.value = '';
};

const processLeaveRequest = async (id, action) => {
    if (action === 'reject') {
        const shouldReject = await confirm({
            title: 'Tolak Pengajuan Izin?',
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

    router.patch(route(`admin.leaves.${action}`, id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const message = action === 'approve'
                ? 'Pengajuan izin berhasil disetujui.'
                : 'Pengajuan izin berhasil ditolak.';
            notify.success(message);
        },
        onError: () => {
            const message = action === 'approve'
                ? 'Gagal menyetujui pengajuan izin.'
                : 'Gagal menolak pengajuan izin.';
            notify.error(message);
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
    <Head title="Monitoring Izin" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
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
            </div>

            <div class="bg-white border border-gray-100 rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
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

            <div class="bg-white border border-gray-100 rounded-xl p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500 border-b border-gray-100">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Tanggal</th>
                                <th class="py-2 pe-4 font-medium">Karyawan</th>
                                <th class="py-2 pe-4 font-medium">Jenis</th>
                                <th class="py-2 pe-4 font-medium">Bukti</th>
                                <th class="py-2 pe-4 font-medium">Status</th>
                                <th class="py-2 pe-4 font-medium">Disetujui Oleh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-for="leaveRequest in leaveRequests.data" :key="leaveRequest.id">
                                <td class="py-3 pe-4 whitespace-nowrap">
                                    <p>{{ formatDate(leaveRequest.leave_date) }}</p>
                                </td>
                                <td class="py-3 pe-4">
                                    <p class="font-medium text-gray-900">{{ leaveRequest.employee_name }}</p>
                                    <p class="text-xs text-gray-500">{{ leaveRequest.id_number ?? '-' }}</p>
                                    <p class="text-xs text-gray-500 mt-1 max-w-[260px] truncate" :title="leaveRequest.reason">{{ leaveRequest.reason ?? '-' }}</p>
                                </td>
                                <td class="py-3 pe-4">{{ leaveRequest.leave_type }}</td>
                                <td class="py-3 pe-4">
                                    <div class="flex flex-col gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                            @click="openProofModal(leaveRequest)"
                                        >
                                            Lihat Bukti
                                        </button>
                                        <a
                                            v-if="leaveRequest.proof_photo_download_url"
                                            :href="leaveRequest.proof_photo_download_url"
                                            class="inline-flex items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                                        >
                                            Unduh
                                        </a>
                                    </div>
                                </td>
                                <td class="py-3 pe-4">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(leaveRequest.approval_status)">
                                        {{ statusLabel(leaveRequest.approval_status) }}
                                    </span>
                                    <div v-if="leaveRequest.approval_status === 'Pending'" class="mt-2 flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                            :disabled="actionLoadingId === leaveRequest.id"
                                            @click="processLeaveRequest(leaveRequest.id, 'approve')"
                                        >
                                            <span v-if="actionLoadingId === leaveRequest.id && actionLoadingType === 'approve'">...</span>
                                            <span v-else>Setujui</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60 dark:bg-rose-600 dark:hover:bg-rose-500"
                                            :disabled="actionLoadingId === leaveRequest.id"
                                            @click="processLeaveRequest(leaveRequest.id, 'reject')"
                                        >
                                            <span v-if="actionLoadingId === leaveRequest.id && actionLoadingType === 'reject'">...</span>
                                            <span v-else>Tolak</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3 pe-4">{{ leaveRequest.approved_by ?? '-' }}</td>
                            </tr>
                            <tr v-if="leaveRequests.data.length === 0">
                                <td colspan="6" class="py-4 text-center text-gray-500">Data izin tidak ditemukan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="leaveRequests.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="(link, index) in leaveRequests.links"
                        :key="index"
                        :disabled="!link.url || link.active"
                        class="rounded-md border px-3 py-1.5 text-sm disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
                        :class="link.active ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900' : 'border-gray-300 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'"
                        @click="visitPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>

            <Modal :show="showProofModal" max-width="4xl" @close="closeProofModal">
                <div class="p-4 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ selectedProofTitle }}</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">{{ selectedProofMeta }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="closeProofModal"
                        >
                            Tutup
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            v-if="selectedProofDownloadUrl"
                            :href="selectedProofDownloadUrl"
                            class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                        >
                            Unduh Bukti
                        </a>
                    </div>

                    <div class="mt-3 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
                        <img
                            v-if="selectedProofSrc"
                            :src="selectedProofSrc"
                            :alt="selectedProofTitle"
                            class="max-h-[70vh] w-full object-contain"
                        >
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
