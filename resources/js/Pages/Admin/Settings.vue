<script setup>
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    setting: {
        type: Object,
        required: true,
    },
});

const GOOGLE_MAPS_DEFAULT_LAT = -0.8917;
const GOOGLE_MAPS_DEFAULT_LNG = 119.8707;
const DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES = 20;
const DEFAULT_CHECK_IN_MAX_LATE_MINUTES = 40;
const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY ?? '';

const notify = useGlobalNotify();
const hasMapsKey = computed(() => googleMapsApiKey.trim().length > 0);
const mapContainerRef = ref(null);
const mapLoading = ref(false);
const mapError = ref('');
const currentLocationLoading = ref(false);
let mapResizeFrameId = null;
let mapResizeTimeoutId = null;

const form = useForm({
    latitude: props.setting.latitude ?? '',
    longitude: props.setting.longitude ?? '',
    radius_meters: Number(props.setting.radius_meters ?? 100),
    check_in_time: props.setting.check_in_time ?? '',
    check_in_late_tolerance_minutes: Number(props.setting.check_in_late_tolerance_minutes ?? DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES),
    check_in_max_late_minutes: Number(props.setting.check_in_max_late_minutes ?? DEFAULT_CHECK_IN_MAX_LATE_MINUTES),
    check_out_time: props.setting.check_out_time ?? '',
});

let mapInstance = null;
let markerInstance = null;
let radiusCircleInstance = null;
const mapListeners = [];

const parseCoordinate = (value, fallback) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizeRadius = (value) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed < 1) {
        return 1;
    }

    return Math.round(parsed);
};

const normalizeMinutes = (value, fallback) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return fallback;
    }

    return Math.round(parsed);
};

const getInitialPosition = () => ({
    lat: parseCoordinate(form.latitude, GOOGLE_MAPS_DEFAULT_LAT),
    lng: parseCoordinate(form.longitude, GOOGLE_MAPS_DEFAULT_LNG),
});

const currentLatitude = computed(() => parseCoordinate(form.latitude, GOOGLE_MAPS_DEFAULT_LAT).toFixed(6));
const currentLongitude = computed(() => parseCoordinate(form.longitude, GOOGLE_MAPS_DEFAULT_LNG).toFixed(6));

const summaryCards = computed(() => [
    {
        label: 'Radius Aktif',
        value: `${normalizeRadius(form.radius_meters)} m`,
    },
    {
        label: 'Jam Masuk',
        value: form.check_in_time || '--:--',
    },
    {
        label: 'Toleransi Normal',
        value: `${normalizeMinutes(form.check_in_late_tolerance_minutes, DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES)} menit`,
    },
    {
        label: 'Batas Maksimal',
        value: `${normalizeMinutes(form.check_in_max_late_minutes, DEFAULT_CHECK_IN_MAX_LATE_MINUTES)} menit`,
    },
    {
        label: 'Jam Pulang',
        value: form.check_out_time || '--:--',
    },
]);

const inputClass = (hasError) => [
    'mt-2 block w-full rounded-2xl border bg-white px-4 py-3 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100 dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-500 dark:focus:ring-blue-500/10',
    hasError ? 'border-red-300 dark:border-red-500/40' : 'border-blue-100 dark:border-blue-200',
];

const handleLayoutResize = () => {
    if (!mapInstance || !window.google?.maps) {
        return;
    }

    const center = getInitialPosition();
    window.google.maps.event.trigger(mapInstance, 'resize');
    mapInstance.setCenter(center);
    syncMapMarkerAndCircle(center.lat, center.lng);
    updateRadiusCircle();
};

const scheduleLayoutResize = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (mapResizeFrameId) {
        window.cancelAnimationFrame(mapResizeFrameId);
    }

    if (mapResizeTimeoutId) {
        window.clearTimeout(mapResizeTimeoutId);
    }

    mapResizeFrameId = window.requestAnimationFrame(() => {
        handleLayoutResize();

        mapResizeTimeoutId = window.setTimeout(() => {
            handleLayoutResize();
        }, 120);
    });
};

const syncMapMarkerAndCircle = (lat, lng, moveCamera = false) => {
    if (!mapInstance || !markerInstance || !radiusCircleInstance) {
        return;
    }

    const latLng = { lat, lng };
    markerInstance.setPosition(latLng);
    radiusCircleInstance.setCenter(latLng);

    if (moveCamera) {
        mapInstance.panTo(latLng);
    }
};

const updateCoordinatesFromMap = (lat, lng, moveCamera = false) => {
    form.latitude = lat.toFixed(6);
    form.longitude = lng.toFixed(6);
    syncMapMarkerAndCircle(lat, lng, moveCamera);
};

const updateRadiusCircle = () => {
    form.radius_meters = normalizeRadius(form.radius_meters);

    if (radiusCircleInstance) {
        radiusCircleInstance.setRadius(form.radius_meters);
    }
};

const geolocationErrorMessage = (error) => {
    if (error?.code === 1) {
        return 'Izin akses lokasi ditolak. Aktifkan izin lokasi pada browser Anda.';
    }

    if (error?.code === 2) {
        return 'Lokasi saat ini belum dapat ditemukan. Pastikan GPS atau layanan lokasi aktif.';
    }

    if (error?.code === 3) {
        return 'Waktu pengambilan lokasi habis. Coba ulangi beberapa saat lagi.';
    }

    return 'Gagal mengambil lokasi saat ini.';
};

const useCurrentLocation = () => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
        const message = 'Browser ini tidak mendukung fitur ambil lokasi saat ini.';
        mapError.value = message;
        notify.error(message);
        return;
    }

    currentLocationLoading.value = true;
    mapError.value = '';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            updateCoordinatesFromMap(position.coords.latitude, position.coords.longitude, true);
            notify.success('Lokasi saat ini berhasil diambil.');
            currentLocationLoading.value = false;
        },
        (error) => {
            const message = geolocationErrorMessage(error);
            mapError.value = message;
            notify.error(message);
            currentLocationLoading.value = false;
        },
        {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 15000,
        },
    );
};

const waitForGoogleMaps = (timeoutMs = 10000, intervalMs = 50) => new Promise((resolve, reject) => {
    const startedAt = Date.now();

    const checkAvailability = () => {
        if (window.google?.maps) {
            resolve(window.google.maps);
            return;
        }

        if (Date.now() - startedAt >= timeoutMs) {
            reject(new Error('Google Maps tidak tersedia setelah script dimuat.'));
            return;
        }

        window.setTimeout(checkAvailability, intervalMs);
    };

    checkAvailability();
});

const loadGoogleMapsApi = () => {
    if (window.google?.maps) {
        return Promise.resolve(window.google.maps);
    }

    if (!hasMapsKey.value) {
        return Promise.reject(new Error('Google Maps API key belum diatur.'));
    }

    if (window.__googleMapsApiPromise) {
        return window.__googleMapsApiPromise;
    }

    const resolveWhenReady = (resolve, reject) => {
        waitForGoogleMaps()
            .then((maps) => resolve(maps))
            .catch((error) => reject(error));
    };

    const mapApiPromise = new Promise((resolve, reject) => {
        const scriptId = 'google-maps-js-api';
        const existingScript = document.getElementById(scriptId);

        if (existingScript) {
            existingScript.addEventListener('load', () => resolveWhenReady(resolve, reject), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('Gagal memuat script Google Maps.')), { once: true });
            resolveWhenReady(resolve, reject);
            return;
        }

        const script = document.createElement('script');
        script.id = scriptId;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(googleMapsApiKey)}&v=weekly`;
        script.async = true;
        script.defer = true;
        script.onload = () => resolveWhenReady(resolve, reject);
        script.onerror = () => reject(new Error('Gagal memuat Google Maps. Cek API key atau koneksi internet.'));

        document.head.appendChild(script);
    });

    window.__googleMapsApiPromise = mapApiPromise.catch((error) => {
        window.__googleMapsApiPromise = null;
        throw error;
    });

    return window.__googleMapsApiPromise;
};

const initMap = async () => {
    if (!hasMapsKey.value || !mapContainerRef.value) {
        return;
    }

    mapLoading.value = true;
    mapError.value = '';

    try {
        const maps = await loadGoogleMapsApi();
        const center = getInitialPosition();

        mapInstance = new maps.Map(mapContainerRef.value, {
            center,
            zoom: 17,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
        });

        markerInstance = new maps.Marker({
            map: mapInstance,
            position: center,
            draggable: true,
            title: 'Lokasi Kantor',
        });

        radiusCircleInstance = new maps.Circle({
            map: mapInstance,
            center,
            radius: normalizeRadius(form.radius_meters),
            fillColor: '#2563eb',
            fillOpacity: 0.12,
            strokeColor: '#1d4ed8',
            strokeOpacity: 0.6,
            strokeWeight: 1,
        });

        mapListeners.push(
            mapInstance.addListener('click', (event) => {
                if (!event.latLng) {
                    return;
                }

                updateCoordinatesFromMap(event.latLng.lat(), event.latLng.lng());
            }),
        );

        mapListeners.push(
            markerInstance.addListener('dragend', (event) => {
                if (!event.latLng) {
                    return;
                }

                updateCoordinatesFromMap(event.latLng.lat(), event.latLng.lng());
            }),
        );

        scheduleLayoutResize();

        if (typeof window !== 'undefined') {
            window.setTimeout(() => {
                scheduleLayoutResize();
            }, 180);
        }
    } catch (error) {
        mapError.value = error instanceof Error ? error.message : 'Terjadi error saat memuat Google Maps.';
    } finally {
        mapLoading.value = false;
    }
};

const submit = () => {
    form.radius_meters = normalizeRadius(form.radius_meters);
    form.check_in_late_tolerance_minutes = normalizeMinutes(
        form.check_in_late_tolerance_minutes,
        DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES,
    );
    form.check_in_max_late_minutes = normalizeMinutes(
        form.check_in_max_late_minutes,
        DEFAULT_CHECK_IN_MAX_LATE_MINUTES,
    );

    form.put(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            notify.success('Pengaturan berhasil diperbarui.');
        },
        onError: () => {
            notify.error('Gagal memperbarui pengaturan. Periksa kembali input Anda.');
        },
    });
};

watch(
    () => [form.latitude, form.longitude],
    ([lat, lng]) => {
        if (!mapInstance || !markerInstance || !radiusCircleInstance) {
            return;
        }

        const parsedLat = Number(lat);
        const parsedLng = Number(lng);

        if (!Number.isFinite(parsedLat) || !Number.isFinite(parsedLng)) {
            return;
        }

        syncMapMarkerAndCircle(parsedLat, parsedLng);
    },
);

watch(
    () => form.radius_meters,
    () => {
        updateRadiusCircle();
    },
);

onMounted(() => {
    initMap();

    if (typeof window !== 'undefined') {
        window.addEventListener('resize', scheduleLayoutResize);
        window.addEventListener('admin-layout:resize', scheduleLayoutResize);
    }
});

onBeforeUnmount(() => {
    mapListeners.forEach((listener) => listener.remove());
    mapListeners.length = 0;

    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', scheduleLayoutResize);
        window.removeEventListener('admin-layout:resize', scheduleLayoutResize);
    }

    if (typeof window !== 'undefined' && mapResizeFrameId) {
        window.cancelAnimationFrame(mapResizeFrameId);
    }

    if (typeof window !== 'undefined' && mapResizeTimeoutId) {
        window.clearTimeout(mapResizeTimeoutId);
    }
});
</script>

<template>
    <Head title="Pengaturan Lokasi & Jam Kerja" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Pengaturan</h2>
                <div class="mt-3 grid gap-3 md:grid-cols-3 xl:grid-cols-5">
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

            <section class="grid items-start gap-4 2xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.75fr)]">
                <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Peta Lokasi</p>
                            <h3 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Titik kantor dan radius presensi</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                Sesuaikan posisi kantor langsung dari peta agar koordinat lebih akurat.
                            </p>
                        </div>
                        <div class="flex flex-col gap-2 sm:items-end">
                            <button
                                type="button"
                                :disabled="currentLocationLoading"
                                class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-200 dark:bg-blue-50 dark:text-blue-700 dark:hover:bg-blue-100"
                                @click="useCurrentLocation"
                            >
                                {{ currentLocationLoading ? 'Mengambil Lokasi...' : 'Ambil Lokasi Saat Ini' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="!hasMapsKey" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700 dark:border-red-200 dark:bg-red-50 dark:text-red-700">
                        Google Maps belum aktif. Isi `VITE_GOOGLE_MAPS_API_KEY` di `.env`, lalu jalankan ulang Vite.
                    </div>

                    <div v-else class="mt-6 relative">
                        <div ref="mapContainerRef" class="h-[20rem] w-full rounded-lg border border-slate-200 bg-slate-100 md:h-[24rem] dark:border-slate-800 dark:bg-slate-900" />
                        <div v-if="mapLoading" class="absolute inset-0 grid place-items-center rounded-lg bg-white/70 text-sm text-slate-700 dark:bg-slate-950/70 dark:text-slate-200">
                            Memuat Google Maps...
                        </div>
                    </div>

                    <p v-if="mapError" class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ mapError }}</p>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Latitude</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ currentLatitude }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Longitude</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ currentLongitude }}</p>
                        </div>
                    </div>
                </section>

                <div class="min-w-0 space-y-4">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Form Pengaturan</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Update konfigurasi</h3>

                        <form class="mt-6 space-y-4" @submit.prevent="submit">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Latitude Kantor</label>
                                <input v-model="form.latitude" type="number" step="any" placeholder="-0.891700" :class="inputClass(!!form.errors.latitude)" />
                                <p v-if="form.errors.latitude" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.latitude }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Longitude Kantor</label>
                                <input v-model="form.longitude" type="number" step="any" placeholder="119.870700" :class="inputClass(!!form.errors.longitude)" />
                                <p v-if="form.errors.longitude" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.longitude }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Radius Presensi (meter)</label>
                                <input v-model.number="form.radius_meters" type="number" min="1" :class="inputClass(!!form.errors.radius_meters)" required />
                                <input v-model.number="form.radius_meters" type="range" min="10" max="5000" step="10" class="mt-4 w-full accent-blue-600" />
                                <p v-if="form.errors.radius_meters" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.radius_meters }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Masuk</label>
                                    <input v-model="form.check_in_time" type="time" :class="inputClass(!!form.errors.check_in_time)" />
                                    <p v-if="form.errors.check_in_time" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.check_in_time }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Pulang</label>
                                    <input v-model="form.check_out_time" type="time" :class="inputClass(!!form.errors.check_out_time)" />
                                    <p v-if="form.errors.check_out_time" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.check_out_time }}</p>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Toleransi Normal Masuk (menit)</label>
                                    <input v-model.number="form.check_in_late_tolerance_minutes" type="number" min="0" max="1440" :class="inputClass(!!form.errors.check_in_late_tolerance_minutes)" />
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Lewat batas ini, presensi masuk ditandai Lambat.</p>
                                    <p v-if="form.errors.check_in_late_tolerance_minutes" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.check_in_late_tolerance_minutes }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Batas Maksimal Terlambat (menit)</label>
                                    <input v-model.number="form.check_in_max_late_minutes" type="number" min="0" max="1440" :class="inputClass(!!form.errors.check_in_max_late_minutes)" />
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Lewat batas ini, absen masuk ditutup.</p>
                                    <p v-if="form.errors.check_in_max_late_minutes" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.check_in_max_late_minutes }}</p>
                                </div>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-700 dark:text-white dark:hover:bg-blue-800"
                            >
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                            </button>
                        </form>
                    </section>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
