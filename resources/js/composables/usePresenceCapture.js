import { router } from '@inertiajs/vue3';
import { nextTick, ref, unref } from 'vue';

export const usePresenceCapture = ({ notify, officeReady, unavailableMessage = 'Lokasi kantor belum dikonfigurasi admin.' }) => {
    const videoRef = ref(null);
    const cameraLoading = ref(false);
    const cameraReady = ref(false);
    const cameraError = ref('');
    const locationLoading = ref(false);
    const locationError = ref('');
    const currentPosition = ref(null);
    const capturedPhoto = ref('');
    const captureTimestamp = ref('');
    const activeAction = ref('');
    let mediaStream = null;

    const firstErrorMessage = (errors) => {
        const firstValue = Object.values(errors ?? {}).find((value) => value);

        if (Array.isArray(firstValue)) {
            return firstValue[0];
        }

        return firstValue ?? 'Terjadi kesalahan saat memproses permintaan.';
    };

    const stopCamera = () => {
        if (mediaStream) {
            mediaStream.getTracks().forEach((track) => track.stop());
            mediaStream = null;
        }

        if (videoRef.value) {
            videoRef.value.srcObject = null;
        }

        cameraReady.value = false;
    };

    const startCamera = async () => {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
            cameraError.value = 'Browser ini belum mendukung akses kamera.';
            throw new Error(cameraError.value);
        }

        if (mediaStream && cameraReady.value) {
            return;
        }

        cameraLoading.value = true;
        cameraError.value = '';

        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });

            await nextTick();

            if (!videoRef.value) {
                throw new Error('Preview kamera belum siap.');
            }

            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play();
            cameraReady.value = true;
        } catch (error) {
            stopCamera();
            cameraError.value = error instanceof Error ? error.message : 'Kamera tidak bisa diakses.';
            throw error instanceof Error ? error : new Error(cameraError.value);
        } finally {
            cameraLoading.value = false;
        }
    };

    const ensureLocation = () => new Promise((resolve, reject) => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            locationError.value = 'Browser ini belum mendukung GPS/geolocation.';
            reject(new Error(locationError.value));
            return;
        }

        locationLoading.value = true;
        locationError.value = '';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentPosition.value = {
                    latitude: Number(position.coords.latitude),
                    longitude: Number(position.coords.longitude),
                    accuracy: Math.round(position.coords.accuracy ?? 0),
                };
                locationLoading.value = false;
                resolve(currentPosition.value);
            },
            (error) => {
                locationLoading.value = false;
                locationError.value = error.message || 'Lokasi tidak bisa diambil.';
                reject(new Error(locationError.value));
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });

    const captureSnapshot = () => {
        if (!videoRef.value || !cameraReady.value) {
            throw new Error('Aktifkan kamera sebelum mengambil foto presensi.');
        }

        const canvas = document.createElement('canvas');
        const width = videoRef.value.videoWidth || 1280;
        const height = videoRef.value.videoHeight || 720;

        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');
        if (!context) {
            throw new Error('Gagal memproses frame kamera.');
        }

        context.drawImage(videoRef.value, 0, 0, width, height);
        capturedPhoto.value = canvas.toDataURL('image/jpeg', 0.92);
        captureTimestamp.value = new Date().toLocaleTimeString('id-ID', {
            timeZone: 'Asia/Makassar',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });

        return capturedPhoto.value;
    };

    const postPresence = (routeName, payload, successMessage, routeParams = undefined) => new Promise((resolve, reject) => {
        router.post(route(routeName, routeParams), payload, {
            preserveScroll: true,
            onSuccess: () => {
                notify.success(successMessage);
                resolve();
            },
            onError: (errors) => {
                const message = firstErrorMessage(errors);
                notify.error(message);
                reject(new Error(message));
            },
        });
    });

    const submitPresence = async ({ actionKey, routeName, successMessage, routeParams = undefined }) => {
        if (!unref(officeReady)) {
            notify.error(unavailableMessage);
            return;
        }

        activeAction.value = actionKey;

        try {
            await startCamera();
            await ensureLocation();
            const photo = captureSnapshot();

            await postPresence(routeName, {
                latitude: currentPosition.value.latitude,
                longitude: currentPosition.value.longitude,
                photo,
            }, successMessage, routeParams);
        } catch (error) {
            if (error instanceof Error && !cameraError.value && !locationError.value) {
                notify.error(error.message);
            }
        } finally {
            activeAction.value = '';
        }
    };

    return {
        activeAction,
        cameraError,
        cameraLoading,
        cameraReady,
        captureTimestamp,
        capturedPhoto,
        currentPosition,
        firstErrorMessage,
        locationError,
        locationLoading,
        startCamera,
        stopCamera,
        captureSnapshot,
        submitPresence,
        ensureLocation,
        videoRef,
    };
};
