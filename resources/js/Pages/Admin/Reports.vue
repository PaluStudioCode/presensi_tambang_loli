<script setup>
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
    attendanceByEmployee: {
        type: Array,
        required: true,
    },
    overtimeByEmployee: {
        type: Array,
        required: true,
    },
    attendanceDetails: {
        type: Array,
        required: true,
    },
    overtimeDetails: {
        type: Array,
        required: true,
    },
});

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    employee_id: props.filters.employee_id ?? '',
});

let filterDebounceTimeoutId = null;
const exportingReport = ref(null);

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

const statusLabel = (status) => ({
    Pending: 'Menunggu',
    Approved: 'Disetujui',
    Rejected: 'Ditolak',
}[status] ?? status);

const applyFilter = () => {
    router.get(route('admin.reports.index'), {
        date_from: form.date_from,
        date_to: form.date_to,
        employee_id: form.employee_id || null,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilter = () => {
    form.employee_id = '';
};

const reportQuery = () => ({
    date_from: form.date_from,
    date_to: form.date_to,
    employee_id: form.employee_id || null,
});

const fallbackFilename = (type) => {
    const prefix = type === 'attendance' ? 'laporan-presensi' : 'laporan-lembur';

    return `${prefix}-${form.date_from}-sd-${form.date_to}.xls`;
};

const extractFilename = (response, fallback) => {
    const disposition = response.headers.get('Content-Disposition');

    if (!disposition) {
        return fallback;
    }

    const encodedFilename = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    if (encodedFilename) {
        return decodeURIComponent(encodedFilename);
    }

    return disposition.match(/filename="?([^"]+)"?/i)?.[1] ?? fallback;
};

const triggerDownload = (blob, filename) => {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    window.setTimeout(() => URL.revokeObjectURL(url), 100);
};

const exportExcel = async (type) => {
    if (exportingReport.value) {
        return;
    }

    const routeName = type === 'attendance'
        ? 'admin.reports.attendance.excel'
        : 'admin.reports.overtime.excel';

    exportingReport.value = type;

    try {
        const response = await fetch(route(routeName, reportQuery()), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/vnd.ms-excel',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Ekspor gagal');
        }

        triggerDownload(
            await response.blob(),
            extractFilename(response, fallbackFilename(type)),
        );
    } catch (error) {
        window.alert('Gagal export laporan. Silakan coba lagi.');
    } finally {
        exportingReport.value = null;
    }
};

watch(
    () => [form.date_from, form.date_to, form.employee_id],
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
    <Head title="Laporan Lengkap" />

    <AuthenticatedLayout>
        <div class="space-y-4">
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
                            <label class="block text-xs text-gray-500">Karyawan</label>
                            <select v-model="form.employee_id" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                <option value="">Semua Karyawan</option>
                                <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                    {{ employee.full_name }} ({{ employee.id_number }})
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2 md:col-span-2">
                            <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="resetFilter">
                                Atur Ulang
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-blue-200 bg-blue-50 text-blue-700 transition hover:bg-blue-100 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                                :class="{ 'cursor-wait opacity-70': exportingReport === 'attendance' }"
                                :disabled="exportingReport !== null"
                                aria-label="Ekspor presensi Excel"
                                title="Ekspor presensi Excel"
                                @click="exportExcel('attendance')"
                            >
                                <svg v-if="exportingReport === 'attendance'" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 3a9 9 0 1 1-8.2 5.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                                <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 3v10m0 0 4-4m-4 4-4-4M5 17v1.5A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                                :class="{ 'cursor-wait opacity-70': exportingReport === 'overtime' }"
                                :disabled="exportingReport !== null"
                                aria-label="Ekspor lembur Excel"
                                title="Ekspor lembur Excel"
                                @click="exportExcel('overtime')"
                            >
                                <svg v-if="exportingReport === 'overtime'" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 3a9 9 0 1 1-8.2 5.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                                <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 6v6l3 2M12 3a9 9 0 1 0 9 9M19 3v5h-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Total Karyawan</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.totalEmployees }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Data Presensi</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.attendanceRecords }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Karyawan Hadir</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.attendanceEmployees }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Presensi Terlambat</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.lateAttendance }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4">
                        <p class="text-xs text-gray-500">Jam Lembur Disetujui</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.approvedHours }} jam</p>
                    </div>
                </div>

                <div class="grid items-stretch grid-cols-1 xl:grid-cols-2 gap-4">
                    <div class="flex h-[32rem] min-h-0 flex-col bg-white border border-gray-100 rounded-xl p-4">
                        <h3 class="text-lg font-semibold text-gray-900">Rekap Presensi per Karyawan</h3>
                        <div class="mt-4 min-h-0 flex-1 overflow-auto pe-1">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">Karyawan</th>
                                        <th class="py-2 pe-4 font-medium">Hari Hadir</th>
                                        <th class="py-2 pe-4 font-medium">Selesai</th>
                                        <th class="py-2 pe-4 font-medium">Rentang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <tr v-for="(row, index) in attendanceByEmployee" :key="index">
                                        <td class="py-3 pe-4">
                                            <p class="font-medium text-gray-900">{{ row.employee_name }}</p>
                                            <p class="text-xs text-gray-500">{{ row.id_number ?? '-' }}</p>
                                        </td>
                                        <td class="py-3 pe-4">{{ row.attendance_days }}</td>
                                        <td class="py-3 pe-4">{{ row.completed_days }}</td>
                                        <td class="py-3 pe-4 text-xs">
                                            {{ formatDate(row.first_attendance) }} - {{ formatDate(row.last_attendance) }}
                                        </td>
                                    </tr>
                                    <tr v-if="attendanceByEmployee.length === 0">
                                        <td colspan="4" class="py-4 text-center text-gray-500">Tidak ada data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex h-[32rem] min-h-0 flex-col bg-white border border-gray-100 rounded-xl p-4">
                        <h3 class="text-lg font-semibold text-gray-900">Rekap Lembur per Karyawan</h3>
                        <div class="mt-4 min-h-0 flex-1 overflow-auto pe-1">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">Karyawan</th>
                                        <th class="py-2 pe-4 font-medium">Total</th>
                                        <th class="py-2 pe-4 font-medium">Menunggu</th>
                                        <th class="py-2 pe-4 font-medium">Disetujui</th>
                                        <th class="py-2 pe-4 font-medium">Jam</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <tr v-for="(row, index) in overtimeByEmployee" :key="index">
                                        <td class="py-3 pe-4">
                                            <p class="font-medium text-gray-900">{{ row.employee_name }}</p>
                                            <p class="text-xs text-gray-500">{{ row.id_number ?? '-' }}</p>
                                        </td>
                                        <td class="py-3 pe-4">{{ row.total_requests }}</td>
                                        <td class="py-3 pe-4">{{ row.pending }}</td>
                                        <td class="py-3 pe-4">{{ row.approved }}</td>
                                        <td class="py-3 pe-4">{{ row.approved_hours }} jam</td>
                                    </tr>
                                    <tr v-if="overtimeByEmployee.length === 0">
                                        <td colspan="5" class="py-4 text-center text-gray-500">Tidak ada data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="grid items-stretch grid-cols-1 xl:grid-cols-2 gap-4">
                    <div class="flex h-[32rem] min-h-0 flex-col bg-white border border-gray-100 rounded-xl p-4">
                        <h3 class="text-lg font-semibold text-gray-900">Detail Presensi (100 terbaru)</h3>
                        <div class="mt-4 min-h-0 flex-1 overflow-auto pe-1">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">Tanggal</th>
                                        <th class="py-2 pe-4 font-medium">Karyawan</th>
                                        <th class="py-2 pe-4 font-medium">Masuk</th>
                                        <th class="py-2 pe-4 font-medium">Pulang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <tr v-for="(row, index) in attendanceDetails" :key="index">
                                        <td class="py-3 pe-4">{{ formatDate(row.date) }}</td>
                                        <td class="py-3 pe-4">{{ row.employee_name }}</td>
                                        <td class="py-3 pe-4">{{ formatTime(row.clock_in_at) }}</td>
                                        <td class="py-3 pe-4">{{ formatTime(row.clock_out_at) }}</td>
                                    </tr>
                                    <tr v-if="attendanceDetails.length === 0">
                                        <td colspan="4" class="py-4 text-center text-gray-500">Tidak ada data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex h-[32rem] min-h-0 flex-col bg-white border border-gray-100 rounded-xl p-4">
                        <h3 class="text-lg font-semibold text-gray-900">Detail Lembur (100 terbaru)</h3>
                        <div class="mt-4 min-h-0 flex-1 overflow-auto pe-1">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">Tanggal</th>
                                        <th class="py-2 pe-4 font-medium">Karyawan</th>
                                        <th class="py-2 pe-4 font-medium">Status</th>
                                        <th class="py-2 pe-4 font-medium">Jam</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <tr v-for="(row, index) in overtimeDetails" :key="index">
                                        <td class="py-3 pe-4">{{ formatDate(row.overtime_date) }}</td>
                                        <td class="py-3 pe-4">{{ row.employee_name }}</td>
                                        <td class="py-3 pe-4">{{ statusLabel(row.approval_status) }}</td>
                                        <td class="py-3 pe-4">
                                            {{ formatTime(row.actual_start) }} - {{ formatTime(row.actual_end) }}
                                        </td>
                                    </tr>
                                    <tr v-if="overtimeDetails.length === 0">
                                        <td colspan="4" class="py-4 text-center text-gray-500">Tidak ada data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
        </div>
    </AuthenticatedLayout>
</template>
