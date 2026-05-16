<script setup>
import Modal from '@/Components/Modal.vue';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    leaveRequests: {
        type: Object,
        required: true,
    },
});

const notify = useGlobalNotify();
const proofInputRef = ref(null);
const proofPreviewUrl = ref('');
const showProofModal = ref(false);
const selectedProofSrc = ref('');
const selectedProofTitle = ref('');
const selectedProofMeta = ref('');

const leaveForm = useForm({
    leave_date: new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Makassar' }),
    leave_type: 'Sakit',
    reason: '',
    proof_photo: null,
});

const summaryCards = computed(() => [
    {
        label: 'Total Izin',
        value: props.leaveRequests.total ?? props.leaveRequests.data.length,
    },
    {
        label: 'Menunggu',
        value: props.leaveRequests.data.filter((leaveRequest) => leaveRequest.approval_status === 'Pending').length,
    },
    {
        label: 'Disetujui',
        value: props.leaveRequests.data.filter((leaveRequest) => leaveRequest.approval_status === 'Approved').length,
    },
]);

const inputClass = (hasError) => [
    'mt-2 block w-full rounded-lg border bg-white px-4 py-3 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-amber-500/10',
    hasError ? 'border-rose-300 dark:border-rose-500/40' : 'border-slate-200 dark:border-slate-700',
];

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

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const handleProofChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    leaveForm.proof_photo = file;

    if (proofPreviewUrl.value) {
        URL.revokeObjectURL(proofPreviewUrl.value);
    }

    proofPreviewUrl.value = file ? URL.createObjectURL(file) : '';
};

const submitLeaveRequest = () => {
    leaveForm.post(route('employee.leaves.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            notify.success('Pengajuan izin berhasil dikirim.');
            leaveForm.reset('reason', 'proof_photo');

            if (proofInputRef.value) {
                proofInputRef.value.value = '';
            }

            if (proofPreviewUrl.value) {
                URL.revokeObjectURL(proofPreviewUrl.value);
                proofPreviewUrl.value = '';
            }
        },
        onError: () => {
            notify.error('Gagal mengirim pengajuan izin. Periksa kembali data dan bukti surat.');
        },
    });
};

const openProofModal = (leaveRequest) => {
    if (!leaveRequest.proof_photo) {
        return;
    }

    selectedProofSrc.value = leaveRequest.proof_photo;
    selectedProofTitle.value = `Bukti ${leaveRequest.leave_type}`;
    selectedProofMeta.value = `${formatDate(leaveRequest.leave_date)} - ${statusLabel(leaveRequest.approval_status)}`;
    showProofModal.value = true;
};

const closeProofModal = () => {
    showProofModal.value = false;
    selectedProofSrc.value = '';
    selectedProofTitle.value = '';
    selectedProofMeta.value = '';
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

onBeforeUnmount(() => {
    if (proofPreviewUrl.value) {
        URL.revokeObjectURL(proofPreviewUrl.value);
    }
});
</script>

<template>
    <Head title="Izin" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Izin</h2>

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

            <section class="grid gap-4 xl:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.1fr)]">
                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Pengajuan Izin</p>
                    <h2 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Buat izin baru</h2>

                    <form class="mt-4 space-y-4" @submit.prevent="submitLeaveRequest">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tanggal Izin</label>
                                <input v-model="leaveForm.leave_date" type="date" :class="inputClass(!!leaveForm.errors.leave_date)" required>
                                <p v-if="leaveForm.errors.leave_date" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ leaveForm.errors.leave_date }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jenis Izin</label>
                                <select v-model="leaveForm.leave_type" :class="inputClass(!!leaveForm.errors.leave_type)" required>
                                    <option value="Sakit">Sakit</option>
                                    <option value="Izin">Izin</option>
                                </select>
                                <p v-if="leaveForm.errors.leave_type" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ leaveForm.errors.leave_type }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Alasan</label>
                            <textarea
                                v-model="leaveForm.reason"
                                rows="4"
                                :class="inputClass(!!leaveForm.errors.reason)"
                                placeholder="Contoh: sakit dan perlu istirahat sesuai surat dokter."
                                required
                            ></textarea>
                            <p v-if="leaveForm.errors.reason" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ leaveForm.errors.reason }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Bukti Surat Sakit / Izin</label>
                            <input
                                ref="proofInputRef"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                :class="inputClass(!!leaveForm.errors.proof_photo)"
                                required
                                @change="handleProofChange"
                            >
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Format: JPG, PNG, atau WEBP. Maksimal 5 MB.</p>
                            <p v-if="leaveForm.errors.proof_photo" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ leaveForm.errors.proof_photo }}</p>
                        </div>

                        <div v-if="proofPreviewUrl" class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Pratinjau bukti</p>
                            <img :src="proofPreviewUrl" alt="Pratinjau bukti izin" class="mt-3 max-h-72 w-full rounded-lg object-contain">
                        </div>

                        <button
                            type="submit"
                            :disabled="leaveForm.processing"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                        >
                            {{ leaveForm.processing ? 'Mengirim Izin...' : 'Kirim Pengajuan Izin' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Riwayat Izin</h2>

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="py-2 pe-3 font-medium">Tanggal</th>
                                    <th class="py-2 pe-3 font-medium">Jenis</th>
                                    <th class="py-2 pe-3 font-medium">Status</th>
                                    <th class="py-2 pe-3 font-medium">Bukti</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="leaveRequest in leaveRequests.data" :key="leaveRequest.id">
                                    <td class="py-3 pe-3 whitespace-nowrap">
                                        <p>{{ formatDate(leaveRequest.leave_date) }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ formatDateTime(leaveRequest.created_at) }}</p>
                                    </td>
                                    <td class="py-3 pe-3">
                                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.leave_type }}</p>
                                        <p class="mt-1 max-w-[280px] truncate text-xs text-slate-500 dark:text-slate-400" :title="leaveRequest.reason">{{ leaveRequest.reason }}</p>
                                    </td>
                                    <td class="py-3 pe-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(leaveRequest.approval_status)">
                                            {{ statusLabel(leaveRequest.approval_status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 pe-3">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                            @click="openProofModal(leaveRequest)"
                                        >
                                            Lihat Bukti
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!leaveRequests.data.length">
                                    <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada pengajuan izin.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="leaveRequests.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="(link, index) in leaveRequests.links"
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
            </section>

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

                    <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
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
