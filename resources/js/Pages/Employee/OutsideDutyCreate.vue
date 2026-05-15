<script setup>
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import { usePresenceCapture } from '@/composables/usePresenceCapture';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const notify = useGlobalNotify();
const proofInputRef = ref(null);
const proofPreviewUrl = ref('');
const GOOGLE_MAPS_DEFAULT_LAT = -0.8917;
const GOOGLE_MAPS_DEFAULT_LNG = 119.8707;
const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY ?? '';
const hasMapsKey = computed(() => googleMapsApiKey.trim().length > 0);
const mapContainerRef = ref(null);
const mapLoading = ref(false);
const mapError = ref('');
let mapInstance = null;
let markerInstance = null;
let radiusCircleInstance = null;
let mapResizeFrameId = null;
let mapResizeTimeoutId = null;
const mapListeners = [];

const makassarTodayIso = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Makassar' });
const alwaysReady = computed(() => true);

const {
    currentPosition,
    ensureLocation,
    firstErrorMessage,
    locationError,
    locationLoading,
} = usePresenceCapture({
    notify,
    officeReady: alwaysReady,
});

const outsideDutyForm = useForm({
    duty_date: makassarTodayIso(),
    planned_start: '',
    planned_end: '',
    location_name: '',
    latitude: '',
    longitude: '',
    requested_radius_meters: 100,
    reason: '',
    request_photo: null,
});

const inputClass = (hasError) => [
    'mt-2 block w-full rounded-lg border bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-amber-500/10',
    hasError ? 'border-rose-300 dark:border-rose-500/40' : 'border-slate-200 dark:border-slate-700',
];

const parseCoordinate = (value, fallback) => {
    if (value === '' || value === null || value === undefined) {
        return fallback;
    }

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

const getInitialPosition = () => ({
    lat: parseCoordinate(outsideDutyForm.latitude, GOOGLE_MAPS_DEFAULT_LAT),
    lng: parseCoordinate(outsideDutyForm.longitude, GOOGLE_MAPS_DEFAULT_LNG),
});

const currentLatitude = computed(() => parseCoordinate(outsideDutyForm.latitude, GOOGLE_MAPS_DEFAULT_LAT).toFixed(6));
const currentLongitude = computed(() => parseCoordinate(outsideDutyForm.longitude, GOOGLE_MAPS_DEFAULT_LNG).toFixed(6));

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
    outsideDutyForm.latitude = lat.toFixed(6);
    outsideDutyForm.longitude = lng.toFixed(6);
    syncMapMarkerAndCircle(lat, lng, moveCamera);
};

const updateRadiusCircle = () => {
    outsideDutyForm.requested_radius_meters = normalizeRadius(outsideDutyForm.requested_radius_meters);

    if (radiusCircleInstance) {
        radiusCircleInstance.setRadius(outsideDutyForm.requested_radius_meters);
    }
};

const handleMapResize = () => {
    if (!mapInstance || !window.google?.maps) {
        return;
    }

    const center = getInitialPosition();
    window.google.maps.event.trigger(mapInstance, 'resize');
    mapInstance.setCenter(center);
    syncMapMarkerAndCircle(center.lat, center.lng);
    updateRadiusCircle();
};

const scheduleMapResize = () => {
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
        handleMapResize();

        mapResizeTimeoutId = window.setTimeout(() => {
            handleMapResize();
        }, 120);
    });
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
            title: 'Lokasi Tugas Luar',
        });

        radiusCircleInstance = new maps.Circle({
            map: mapInstance,
            center,
            radius: normalizeRadius(outsideDutyForm.requested_radius_meters),
            fillColor: '#047857',
            fillOpacity: 0.12,
            strokeColor: '#047857',
            strokeOpacity: 0.65,
            strokeWeight: 1,
        });

        mapListeners.push(
            mapInstance.addListener('click', (event) => {
                if (event.latLng) {
                    updateCoordinatesFromMap(event.latLng.lat(), event.latLng.lng());
                }
            }),
        );

        mapListeners.push(
            markerInstance.addListener('dragend', (event) => {
                if (event.latLng) {
                    updateCoordinatesFromMap(event.latLng.lat(), event.latLng.lng());
                }
            }),
        );

        scheduleMapResize();
        window.setTimeout(scheduleMapResize, 180);
    } catch (error) {
        mapError.value = error instanceof Error ? error.message : 'Terjadi error saat memuat Google Maps.';
    } finally {
        mapLoading.value = false;
    }
};

const applyCurrentLocation = async () => {
    const position = await ensureLocation();
    updateCoordinatesFromMap(position.latitude, position.longitude, true);
};

const handleProofChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    outsideDutyForm.request_photo = file;

    if (proofPreviewUrl.value) {
        URL.revokeObjectURL(proofPreviewUrl.value);
    }

    proofPreviewUrl.value = file ? URL.createObjectURL(file) : '';
};

const submitOutsideDuty = async () => {
    try {
        if (!outsideDutyForm.latitude || !outsideDutyForm.longitude) {
            await applyCurrentLocation();
        }

        outsideDutyForm.post(route('employee.outside-duties.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                notify.success('Pengajuan tugas luar berhasil dikirim.');
                outsideDutyForm.reset('planned_start', 'planned_end', 'location_name', 'latitude', 'longitude', 'reason', 'request_photo');
                outsideDutyForm.duty_date = makassarTodayIso();
                outsideDutyForm.requested_radius_meters = 100;

                if (proofInputRef.value) {
                    proofInputRef.value.value = '';
                }

                if (proofPreviewUrl.value) {
                    URL.revokeObjectURL(proofPreviewUrl.value);
                    proofPreviewUrl.value = '';
                }
            },
            onError: (errors) => {
                notify.error(firstErrorMessage(errors));
            },
        });
    } catch (error) {
        if (error instanceof Error) {
            notify.error(error.message);
        }
    }
};

watch(
    () => [outsideDutyForm.latitude, outsideDutyForm.longitude],
    ([lat, lng]) => {
        if (!mapInstance || !markerInstance || !radiusCircleInstance) {
            return;
        }

        if (lat === '' || lng === '' || lat === null || lng === null || lat === undefined || lng === undefined) {
            return;
        }

        const parsedLat = Number(lat);
        const parsedLng = Number(lng);

        if (Number.isFinite(parsedLat) && Number.isFinite(parsedLng)) {
            syncMapMarkerAndCircle(parsedLat, parsedLng);
        }
    },
);

watch(
    () => outsideDutyForm.requested_radius_meters,
    () => {
        updateRadiusCircle();
    },
);

onMounted(() => {
    initMap();

    if (typeof window !== 'undefined') {
        window.addEventListener('resize', scheduleMapResize);
        window.addEventListener('admin-layout:resize', scheduleMapResize);
    }
});

onBeforeUnmount(() => {
    mapListeners.forEach((listener) => listener.remove());
    mapListeners.length = 0;

    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', scheduleMapResize);
        window.removeEventListener('admin-layout:resize', scheduleMapResize);
    }

    if (mapResizeFrameId) {
        window.cancelAnimationFrame(mapResizeFrameId);
    }

    if (mapResizeTimeoutId) {
        window.clearTimeout(mapResizeTimeoutId);
    }

    if (proofPreviewUrl.value) {
        URL.revokeObjectURL(proofPreviewUrl.value);
    }
});
</script>

<template>
    <Head title="Ajukan Tugas Luar" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ajukan Tugas Luar</h2>
                    <Link
                        :href="route('employee.outside-duties.index')"
                        class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Riwayat
                    </Link>
                </div>
            </section>

            <section class="grid items-start gap-4 xl:grid-cols-[minmax(320px,0.78fr)_minmax(0,1.22fr)]">
                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <form class="space-y-4" @submit.prevent="submitOutsideDuty">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tanggal</label>
                            <input v-model="outsideDutyForm.duty_date" type="date" :class="inputClass(!!outsideDutyForm.errors.duty_date)" required>
                            <p v-if="outsideDutyForm.errors.duty_date" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.duty_date }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Mulai</label>
                                <input v-model="outsideDutyForm.planned_start" type="time" :class="inputClass(!!outsideDutyForm.errors.planned_start)" required>
                                <p v-if="outsideDutyForm.errors.planned_start" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.planned_start }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Selesai</label>
                                <input v-model="outsideDutyForm.planned_end" type="time" :class="inputClass(!!outsideDutyForm.errors.planned_end)" required>
                                <p v-if="outsideDutyForm.errors.planned_end" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.planned_end }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nama atau Alamat Lokasi</label>
                            <input v-model="outsideDutyForm.location_name" type="text" :class="inputClass(!!outsideDutyForm.errors.location_name)" placeholder="Contoh: Site Loli Blok Timur" required>
                            <p v-if="outsideDutyForm.errors.location_name" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.location_name }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Alasan atau Keterangan</label>
                            <textarea
                                v-model="outsideDutyForm.reason"
                                rows="4"
                                :class="inputClass(!!outsideDutyForm.errors.reason)"
                                placeholder="Contoh: inspeksi alat berat, kunjungan site, atau koordinasi lapangan."
                                required
                            ></textarea>
                            <p v-if="outsideDutyForm.errors.reason" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.reason }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Bukti Pengajuan</label>
                            <input
                                ref="proofInputRef"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                :class="inputClass(!!outsideDutyForm.errors.request_photo)"
                                required
                                @change="handleProofChange"
                            >
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Upload surat tugas, foto lokasi, atau bukti pendukung. Format JPG, PNG, atau WEBP. Maksimal 5 MB.</p>
                            <p v-if="outsideDutyForm.errors.request_photo" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.request_photo }}</p>
                        </div>

                        <div v-if="proofPreviewUrl" class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Pratinjau bukti</p>
                            <img :src="proofPreviewUrl" alt="Pratinjau bukti tugas luar" class="mt-3 max-h-72 w-full rounded-lg object-contain">
                        </div>

                        <button
                            type="submit"
                            :disabled="outsideDutyForm.processing"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ outsideDutyForm.processing ? 'Mengirim Pengajuan...' : 'Kirim Pengajuan Tugas Luar' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Peta dan GPS</p>
                            <h2 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Pilih titik tugas luar</h2>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            :disabled="locationLoading"
                            @click="applyCurrentLocation"
                        >
                            {{ locationLoading ? 'Mengambil GPS...' : 'Pakai GPS' }}
                        </button>
                    </div>

                    <div v-if="!hasMapsKey" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        Google Maps belum aktif. Isi `VITE_GOOGLE_MAPS_API_KEY` di `.env`, lalu jalankan ulang Vite.
                    </div>

                    <div v-else class="relative mt-4">
                        <div ref="mapContainerRef" class="h-[18rem] w-full rounded-lg border border-slate-200 bg-slate-100 md:h-[22rem] dark:border-slate-800 dark:bg-slate-900" />
                        <div v-if="mapLoading" class="absolute inset-0 grid place-items-center rounded-lg bg-white/70 text-sm text-slate-700 dark:bg-slate-950/70 dark:text-slate-200">
                            Memuat Google Maps...
                        </div>
                    </div>

                    <p v-if="mapError" class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ mapError }}</p>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Latitude</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ currentLatitude }}</p>
                            <p v-if="outsideDutyForm.errors.latitude" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.latitude }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Longitude</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ currentLongitude }}</p>
                            <p v-if="outsideDutyForm.errors.longitude" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.longitude }}</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Radius</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ normalizeRadius(outsideDutyForm.requested_radius_meters) }} m</p>
                        </div>
                    </div>

                    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Radius Diajukan (meter)</label>
                        <input v-model="outsideDutyForm.requested_radius_meters" type="number" min="1" max="50000" :class="inputClass(!!outsideDutyForm.errors.requested_radius_meters)" required>
                        <input v-model.number="outsideDutyForm.requested_radius_meters" type="range" min="10" max="5000" step="10" class="mt-4 w-full accent-emerald-600">
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Radius aktif pada peta: {{ normalizeRadius(outsideDutyForm.requested_radius_meters) }} meter.</p>
                        <p v-if="outsideDutyForm.errors.requested_radius_meters" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ outsideDutyForm.errors.requested_radius_meters }}</p>
                    </div>

                    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                        <p class="text-slate-500 dark:text-slate-400">Lokasi GPS Terakhir</p>
                        <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">
                            {{ currentPosition ? `${currentPosition.latitude.toFixed(6)}, ${currentPosition.longitude.toFixed(6)}` : 'Belum diambil' }}
                        </p>
                        <p class="mt-1 text-slate-500 dark:text-slate-400">
                            Akurasi: {{ currentPosition?.accuracy ? `${currentPosition.accuracy} m` : '-' }}
                        </p>
                    </div>

                    <p v-if="locationError" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ locationError }}
                    </p>
                </section>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
