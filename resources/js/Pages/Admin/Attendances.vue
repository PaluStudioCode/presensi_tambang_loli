<script setup>
import Modal from '@/Components/Modal.vue';
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
    attendances: {
        type: Object,
        required: true,
    },
});

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    employee_id: props.filters.employee_id ?? '',
});

let filterDebounceTimeoutId = null;
const showPhotoModal = ref(false);
const selectedPhotoSrc = ref('');
const selectedPhotoTitle = ref('');
const selectedPhotoMeta = ref('');

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

const clockInStatusClass = (status) => {
    if (status === 'Lambat') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }

    if (status === 'Tepat Waktu') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
};

const applyFilter = () => {
    router.get(route('admin.attendances.index'), {
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

const closePhotoModal = () => {
    showPhotoModal.value = false;
    selectedPhotoSrc.value = '';
    selectedPhotoTitle.value = '';
    selectedPhotoMeta.value = '';
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
    <Head title="Monitoring Presensi" />

    <AuthenticatedLayout>
        <div class="space-y-4">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Total Data</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.totalRecords }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Absen Masuk</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.clockedIn }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Absen Pulang</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.clockedOut }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Terlambat</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.lateCheckIn }}</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
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
                        <div class="flex items-end">
                            <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="resetFilter">
                                Atur Ulang
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500 border-b border-gray-100">
                                <tr>
                                    <th class="py-2 pe-4 font-medium">Tanggal</th>
                                    <th class="py-2 pe-4 font-medium">Karyawan</th>
                                    <th class="py-2 pe-4 font-medium">Masuk</th>
                                    <th class="py-2 pe-4 font-medium">Status</th>
                                    <th class="py-2 pe-4 font-medium">Pulang</th>
                                    <th class="py-2 pe-4 font-medium">Lokasi</th>
                                    <th class="py-2 pe-4 font-medium">Foto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr v-for="attendance in attendances.data" :key="attendance.id">
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="font-medium text-gray-900">{{ attendance.employee_name }}</p>
                                        <p class="text-xs text-gray-500">{{ attendance.id_number ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-3 pe-4 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="clockInStatusClass(attendance.clock_in_status)">
                                            {{ attendance.clock_in_status ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="truncate max-w-[220px]" :title="attendance.clock_in_location ?? '-'">Masuk: {{ attendance.clock_in_location ?? '-' }}</p>
                                        <p class="truncate max-w-[220px]" :title="attendance.clock_out_location ?? '-'">Pulang: {{ attendance.clock_out_location ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-4">
                                        <div class="flex flex-col gap-2">
                                            <button
                                                v-if="attendance.clock_in_photo"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                @click="openPhotoModal(attendance, 'in')"
                                            >
                                                Lihat Foto Masuk
                                            </button>
                                            <button
                                                v-if="attendance.clock_out_photo"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                                @click="openPhotoModal(attendance, 'out')"
                                            >
                                                Lihat Foto Pulang
                                            </button>
                                            <span v-if="!attendance.clock_in_photo && !attendance.clock_out_photo" class="text-xs text-gray-500">-</span>
                                        </div>
                                    </td>
                            </tr>
                            <tr v-if="attendances.data.length === 0">
                                <td colspan="7" class="py-4 text-center text-gray-500">Data presensi tidak ditemukan.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="attendances.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="(link, index) in attendances.links"
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
                            />
                        </div>
                    </div>
                </Modal>
        </div>
    </AuthenticatedLayout>
</template>
