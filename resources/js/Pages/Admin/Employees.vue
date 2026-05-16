<script setup>
import { useGlobalConfirm } from '@/composables/useGlobalConfirm';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    employees: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const search = ref(props.filters.q ?? '');
const editingEmployee = ref(null);
const showEmployeeModal = ref(false);
const confirm = useGlobalConfirm();
const notify = useGlobalNotify();
const hasActiveFilter = computed(() => search.value.trim().length > 0);
const totalFilteredEmployees = computed(() => props.employees.total ?? props.employees.data.length);
const isEditMode = computed(() => editingEmployee.value !== null);
let searchDebounceTimeoutId = null;

const employeeForm = useForm({
    id_number: '',
    full_name: '',
    department: '',
    address: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const summaryCards = computed(() => [
    {
        label: 'Total Karyawan',
        value: props.summary.totalEmployees,
    },
    {
        label: 'Akun Admin',
        value: props.summary.totalAdmins,
    },
    {
        label: 'Hasil Pencarian',
        value: totalFilteredEmployees.value,
    },
]);

const inputClass = (hasError) => [
    'mt-2 block w-full rounded-2xl border bg-white px-4 py-3 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-amber-500/10',
    hasError ? 'border-rose-300 dark:border-rose-500/40' : 'border-slate-200 dark:border-slate-700',
];

const formatDateTime = (value) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const applyFilter = () => {
    const query = search.value.trim();

    router.get(route('admin.employees.index'), { q: query || null }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearEmployeeForm = () => {
    employeeForm.transform((data) => data);
    employeeForm.id_number = '';
    employeeForm.full_name = '';
    employeeForm.department = '';
    employeeForm.address = '';
    employeeForm.email = '';
    employeeForm.password = '';
    employeeForm.password_confirmation = '';
    employeeForm.clearErrors();
};

const openCreateModal = () => {
    editingEmployee.value = null;
    clearEmployeeForm();
    showEmployeeModal.value = true;
};

const closeEmployeeModal = () => {
    showEmployeeModal.value = false;
    editingEmployee.value = null;
    clearEmployeeForm();
};

const startEdit = (employee) => {
    editingEmployee.value = employee;
    clearEmployeeForm();
    employeeForm.id_number = employee.id_number;
    employeeForm.full_name = employee.full_name;
    employeeForm.department = employee.department ?? '';
    employeeForm.address = employee.address ?? '';
    employeeForm.email = employee.email;
    showEmployeeModal.value = true;
};

const submitEmployee = () => {
    if (isEditMode.value && editingEmployee.value) {
        let requestSucceeded = false;

        employeeForm.transform((data) => ({
            id_number: data.id_number,
            full_name: data.full_name,
            department: data.department,
            address: data.address,
            email: data.email,
        })).patch(route('admin.employees.update', editingEmployee.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                requestSucceeded = true;
                notify.success('Data karyawan berhasil diperbarui.');
            },
            onError: () => {
                notify.error('Gagal memperbarui data karyawan.');
            },
            onFinish: () => {
                employeeForm.transform((data) => data);

                if (requestSucceeded) {
                    closeEmployeeModal();
                }
            },
        });
        return;
    }

    let requestSucceeded = false;

    employeeForm.post(route('admin.employees.store'), {
        preserveScroll: true,
        onSuccess: () => {
            requestSucceeded = true;
            notify.success('Karyawan baru berhasil ditambahkan.');
        },
        onError: () => {
            notify.error('Gagal menambahkan karyawan. Periksa kembali input Anda.');
        },
        onFinish: () => {
            if (requestSucceeded) {
                closeEmployeeModal();
            }
        },
    });
};

const removeEmployee = async (employee) => {
    const shouldDelete = await confirm({
        title: 'Hapus Karyawan?',
        message: `Data akun "${employee.full_name}" akan dihapus secara permanen.`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!shouldDelete) {
        return;
    }

    router.delete(route('admin.employees.destroy', employee.id), {
        preserveScroll: true,
        onSuccess: () => {
            notify.success(`Karyawan "${employee.full_name}" berhasil dihapus.`);
        },
        onError: () => {
            notify.error('Gagal menghapus data karyawan.');
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

watch(search, () => {
    if (searchDebounceTimeoutId) {
        window.clearTimeout(searchDebounceTimeoutId);
    }

    searchDebounceTimeoutId = window.setTimeout(() => {
        applyFilter();
    }, 300);
});

onBeforeUnmount(() => {
    if (searchDebounceTimeoutId) {
        window.clearTimeout(searchDebounceTimeoutId);
    }
});
</script>

<template>
    <Head title="Kelola Karyawan" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Daftar Data</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Daftar karyawan</h3>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <button
                            type="button"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                            @click="openCreateModal"
                        >
                            Tambah Karyawan
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 lg:flex-row">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari nama / ID / email / departemen / alamat"
                        :class="inputClass(false)"
                        class="mt-0 lg:flex-1"
                    />
                </div>

                <div class="mt-4 space-y-3 md:hidden">
                    <article
                        v-for="employee in employees.data"
                        :key="employee.id"
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ employee.full_name }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ employee.email }}</p>
                                <p class="mt-2 text-xs font-medium text-blue-700 dark:text-blue-300">{{ employee.department || 'Departemen belum diisi' }}</p>
                            </div>
                            <span class="rounded-lg bg-white px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {{ employee.id_number }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ employee.address || 'Alamat belum diisi.' }}</p>
                        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Dibuat: {{ formatDateTime(employee.created_at) }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <button type="button" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" @click="startEdit(employee)">
                                Edit
                            </button>
                            <button type="button" class="rounded-lg bg-rose-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-rose-700 dark:bg-rose-500 dark:hover:bg-rose-400" @click="removeEmployee(employee)">
                                Hapus
                            </button>
                        </div>
                    </article>
                    <div v-if="employees.data.length === 0" class="rounded-lg border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        Data karyawan belum ada.
                    </div>
                </div>

                <div class="mt-4 hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pe-4 font-medium">Nomor ID</th>
                                <th class="py-3 pe-4 font-medium">Nama</th>
                                <th class="py-3 pe-4 font-medium">Departemen</th>
                                <th class="py-3 pe-4 font-medium">Email</th>
                                <th class="py-3 pe-4 font-medium">Alamat</th>
                                <th class="py-3 pe-4 font-medium">Dibuat</th>
                                <th class="py-3 pe-4 font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                            <tr v-for="employee in employees.data" :key="employee.id">
                                <td class="py-4 pe-4 font-medium text-slate-900 dark:text-slate-100">{{ employee.id_number }}</td>
                                <td class="py-4 pe-4">{{ employee.full_name }}</td>
                                <td class="py-4 pe-4">{{ employee.department || '-' }}</td>
                                <td class="py-4 pe-4">{{ employee.email }}</td>
                                <td class="max-w-xs whitespace-normal break-words py-4 pe-4">{{ employee.address || '-' }}</td>
                                <td class="py-4 pe-4">{{ formatDateTime(employee.created_at) }}</td>
                                <td class="py-4 pe-4">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" @click="startEdit(employee)">
                                            Edit
                                        </button>
                                        <button type="button" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20" @click="removeEmployee(employee)">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="employees.data.length === 0">
                                <td colspan="7" class="py-8 text-center text-slate-500 dark:text-slate-400">Data karyawan belum ada.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="employees.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="(link, index) in employees.links"
                        :key="index"
                        type="button"
                        :disabled="!link.url || link.active"
                        class="inline-flex min-w-[2.75rem] items-center justify-center rounded-lg border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        :class="link.active
                            ? 'border-slate-950 bg-slate-950 text-white dark:border-amber-400 dark:bg-amber-400 dark:text-slate-950'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'"
                        @click="visitPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </section>

            <Modal :show="showEmployeeModal" :closeable="!employeeForm.processing" max-width="2xl" @close="closeEmployeeModal">
                <div class="p-4 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                                {{ isEditMode ? `Edit karyawan: ${editingEmployee.full_name}` : 'Tambah karyawan' }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                                {{ isEditMode ? 'Perbarui data akun karyawan.' : 'Isi data akun baru untuk karyawan.' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="closeEmployeeModal"
                        >
                            Tutup
                        </button>
                    </div>

                    <form class="mt-4 space-y-4" @submit.prevent="submitEmployee">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nomor ID</label>
                            <input v-model="employeeForm.id_number" type="text" :class="inputClass(!!employeeForm.errors.id_number)" required />
                            <p v-if="employeeForm.errors.id_number" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ employeeForm.errors.id_number }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nama Lengkap</label>
                            <input v-model="employeeForm.full_name" type="text" :class="inputClass(!!employeeForm.errors.full_name)" required />
                            <p v-if="employeeForm.errors.full_name" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ employeeForm.errors.full_name }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Departemen</label>
                                <input v-model="employeeForm.department" type="text" :class="inputClass(!!employeeForm.errors.department)" placeholder="Contoh: Operasional Tambang" />
                                <p v-if="employeeForm.errors.department" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ employeeForm.errors.department }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                                <input v-model="employeeForm.email" type="email" :class="inputClass(!!employeeForm.errors.email)" required />
                                <p v-if="employeeForm.errors.email" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ employeeForm.errors.email }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Alamat</label>
                            <textarea v-model="employeeForm.address" rows="3" :class="inputClass(!!employeeForm.errors.address)" placeholder="Masukkan alamat lengkap"></textarea>
                            <p v-if="employeeForm.errors.address" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ employeeForm.errors.address }}</p>
                        </div>
                        <div v-if="!isEditMode" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                                <input v-model="employeeForm.password" type="password" :class="inputClass(!!employeeForm.errors.password)" required />
                                <p v-if="employeeForm.errors.password" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ employeeForm.errors.password }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Konfirmasi Password</label>
                                <input v-model="employeeForm.password_confirmation" type="password" :class="inputClass(false)" required />
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="closeEmployeeModal"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="employeeForm.processing"
                                class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                            >
                                <span
                                    v-if="employeeForm.processing"
                                    class="me-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
                                    aria-hidden="true"
                                />
                                {{ employeeForm.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Simpan Karyawan') }}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
