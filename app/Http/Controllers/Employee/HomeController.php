<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\Setting;
use App\Models\User;
use App\Support\CapturedPhoto;
use App\Support\Geo;
use App\Support\PublicFileUrl;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectAdminToDashboard($request)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('Employee/Home', [
            'setting' => $this->serializeSetting(),
            'todayAttendance' => $this->serializeTodayAttendance($this->findTodayAttendance($user)),
            'approvedTodayOvertimes' => $this->serializeApprovedTodayOvertimes($user),
            'recentAttendances' => $this->serializeRecentAttendances($user, 3),
            'recentOvertimes' => $this->serializeRecentOvertimes($user, 3),
        ]);
    }

    public function attendance(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectAdminToDashboard($request)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('Employee/Attendance', [
            'setting' => $this->serializeSetting(),
            'todayAttendance' => $this->serializeTodayAttendance($this->findTodayAttendance($user)),
            'recentAttendances' => $this->serializeRecentAttendances($user, 7),
        ]);
    }

    public function overtimes(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectAdminToDashboard($request)) {
            return $redirect;
        }

        $user = $request->user();

        return Inertia::render('Employee/Overtimes', [
            'setting' => $this->serializeSetting(),
            'approvedTodayOvertimes' => $this->serializeApprovedTodayOvertimes($user),
            'recentOvertimes' => $this->serializeRecentOvertimes($user, 10),
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        $attendance = Attendance::query()->firstOrNew([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
        ]);

        if ($attendance->clock_in_at) {
            throw ValidationException::withMessages([
                'attendance' => 'Anda sudah melakukan absen masuk hari ini.',
            ]);
        }

        $this->assertCheckInTimeWindow($setting);
        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $attendance->fill([
            'clock_in_at' => now()->format('H:i:s'),
            'clock_in_photo' => CapturedPhoto::storeDataUrl($payload['photo'], 'attendance/clock-in', 'Foto wajah wajib diambil langsung dari kamera.'),
            'clock_in_location' => Geo::formatLocation($payload['latitude'], $payload['longitude']),
        ]);
        $attendance->save();

        return back()->with('success', 'Absen masuk berhasil disimpan.');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first();

        if (! $attendance || ! $attendance->clock_in_at) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen pulang hanya bisa dilakukan setelah absen masuk.',
            ]);
        }

        if ($attendance->clock_out_at) {
            throw ValidationException::withMessages([
                'attendance' => 'Anda sudah melakukan absen pulang hari ini.',
            ]);
        }

        $this->assertCheckOutTimeReached($setting);
        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $attendance->update([
            'clock_out_at' => now()->format('H:i:s'),
            'clock_out_photo' => CapturedPhoto::storeDataUrl($payload['photo'], 'attendance/clock-out', 'Foto wajah wajib diambil langsung dari kamera.'),
            'clock_out_location' => Geo::formatLocation($payload['latitude'], $payload['longitude']),
        ]);

        return back()->with('success', 'Absen pulang berhasil disimpan.');
    }

    public function storeOvertime(Request $request): RedirectResponse
    {
        $user = $this->ensureEmployee($request);

        $validated = $request->validate([
            'overtime_date' => ['required', 'date', 'after_or_equal:today'],
            'planned_start' => ['required', 'date_format:H:i'],
            'planned_end' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
            'request_photo' => ['required', 'string'],
        ]);

        if ($validated['planned_end'] <= $validated['planned_start']) {
            throw ValidationException::withMessages([
                'planned_end' => 'Jam selesai harus setelah jam mulai pada hari yang sama.',
            ]);
        }

        Overtime::query()->create([
            'user_id' => $user->id,
            'overtime_date' => $validated['overtime_date'],
            'planned_start' => $validated['planned_start'],
            'planned_end' => $validated['planned_end'],
            'reason' => $validated['reason'],
            'overtime_request_photo' => CapturedPhoto::storeDataUrl($validated['request_photo'], 'overtime/request', 'Foto wajah wajib diambil langsung dari kamera.'),
            'approval_status' => 'Pending',
            'approved_by' => null,
            'actual_start' => null,
            'overtime_start_photo' => null,
            'actual_end' => null,
            'overtime_end_photo' => null,
        ]);

        return back()->with('success', 'Pengajuan lembur berhasil dikirim dan menunggu approval admin.');
    }

    public function startOvertime(Request $request, Overtime $overtime): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $this->assertOvertimeOwnership($overtime, $user->id);

        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        if ($overtime->approval_status !== 'Approved') {
            throw ValidationException::withMessages([
                'overtime' => 'Presensi lembur hanya tersedia untuk pengajuan yang sudah disetujui.',
            ]);
        }

        if ($overtime->overtime_date !== Carbon::today()->toDateString()) {
            throw ValidationException::withMessages([
                'overtime' => 'Absen mulai lembur hanya bisa dilakukan pada tanggal lembur yang disetujui.',
            ]);
        }

        if ($overtime->actual_start) {
            throw ValidationException::withMessages([
                'overtime' => 'Anda sudah melakukan absen mulai lembur.',
            ]);
        }

        $this->assertOvertimeTimeReached($overtime, 'planned_start', 'mulai');
        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $overtime->update([
            'actual_start' => now()->format('H:i:s'),
            'overtime_start_photo' => CapturedPhoto::storeDataUrl($payload['photo'], 'overtime/start', 'Foto wajah wajib diambil langsung dari kamera.'),
        ]);

        return back()->with('success', 'Absen mulai lembur berhasil disimpan.');
    }

    public function finishOvertime(Request $request, Overtime $overtime): RedirectResponse
    {
        $user = $this->ensureEmployee($request);
        $this->assertOvertimeOwnership($overtime, $user->id);

        $payload = $this->validatePresencePayload($request);
        $setting = $this->getAttendanceSetting();

        if ($overtime->approval_status !== 'Approved') {
            throw ValidationException::withMessages([
                'overtime' => 'Presensi lembur hanya tersedia untuk pengajuan yang sudah disetujui.',
            ]);
        }

        if ($overtime->overtime_date !== Carbon::today()->toDateString()) {
            throw ValidationException::withMessages([
                'overtime' => 'Absen selesai lembur hanya bisa dilakukan pada tanggal lembur yang disetujui.',
            ]);
        }

        if (! $overtime->actual_start) {
            throw ValidationException::withMessages([
                'overtime' => 'Absen selesai lembur hanya bisa dilakukan setelah absen mulai lembur.',
            ]);
        }

        if ($overtime->actual_end) {
            throw ValidationException::withMessages([
                'overtime' => 'Anda sudah melakukan absen selesai lembur.',
            ]);
        }

        $this->assertOvertimeTimeReached($overtime, 'planned_end', 'selesai');
        $this->assertWithinOfficeRadius($payload['latitude'], $payload['longitude'], $setting);

        $overtime->update([
            'actual_end' => now()->format('H:i:s'),
            'overtime_end_photo' => CapturedPhoto::storeDataUrl($payload['photo'], 'overtime/end', 'Foto wajah wajib diambil langsung dari kamera.'),
        ]);

        return back()->with('success', 'Absen selesai lembur berhasil disimpan.');
    }

    private function ensureEmployee(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && $user->role === 'Employee', 403);

        return $user;
    }

    private function redirectAdminToDashboard(Request $request): ?RedirectResponse
    {
        return $request->user()?->role === 'Admin'
            ? redirect()->route('dashboard')
            : null;
    }

    private function serializeSetting(): array
    {
        $setting = Setting::query()->latest('id')->first();

        return [
            'latitude' => $setting?->latitude,
            'longitude' => $setting?->longitude,
            'radius_meters' => $setting?->radius_meters ?? 100,
            'check_in_time' => $setting?->check_in_time ? substr($setting->check_in_time, 0, 5) : null,
            'check_in_late_tolerance_minutes' => $setting?->checkInLateToleranceMinutes() ?? Setting::DEFAULT_CHECK_IN_LATE_TOLERANCE_MINUTES,
            'check_in_max_late_minutes' => $setting?->checkInMaxLateMinutes() ?? Setting::DEFAULT_CHECK_IN_MAX_LATE_MINUTES,
            'check_out_time' => $setting?->check_out_time ? substr($setting->check_out_time, 0, 5) : null,
            'is_configured' => filled($setting?->latitude) && filled($setting?->longitude),
        ];
    }

    private function findTodayAttendance(User $user): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first();
    }

    private function serializeTodayAttendance(?Attendance $attendance): ?array
    {
        if (! $attendance) {
            return null;
        }

        return [
            'id' => $attendance->id,
            'date' => $attendance->date,
            'clock_in_at' => $attendance->clock_in_at,
            'clock_out_at' => $attendance->clock_out_at,
            'clock_in_photo' => PublicFileUrl::make($attendance->clock_in_photo),
            'clock_out_photo' => PublicFileUrl::make($attendance->clock_out_photo),
            'clock_in_location' => $attendance->clock_in_location,
            'clock_out_location' => $attendance->clock_out_location,
        ];
    }

    private function serializeApprovedTodayOvertimes(User $user): array
    {
        return Overtime::query()
            ->where('user_id', $user->id)
            ->whereDate('overtime_date', Carbon::today()->toDateString())
            ->where('approval_status', 'Approved')
            ->orderBy('planned_start')
            ->get()
            ->map(fn (Overtime $overtime): array => [
                'id' => $overtime->id,
                'overtime_date' => $overtime->overtime_date,
                'planned_start' => $overtime->planned_start,
                'planned_end' => $overtime->planned_end,
                'reason' => $overtime->reason,
                'approval_status' => $overtime->approval_status,
                'actual_start' => $overtime->actual_start,
                'actual_end' => $overtime->actual_end,
                'overtime_start_photo' => PublicFileUrl::make($overtime->overtime_start_photo),
                'overtime_end_photo' => PublicFileUrl::make($overtime->overtime_end_photo),
            ])
            ->all();
    }

    private function serializeRecentAttendances(User $user, int $limit): array
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit($limit)
            ->get()
            ->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'date' => $attendance->date,
                'clock_in_at' => $attendance->clock_in_at,
                'clock_out_at' => $attendance->clock_out_at,
                'clock_in_location' => $attendance->clock_in_location,
                'clock_out_location' => $attendance->clock_out_location,
            ])
            ->all();
    }

    private function serializeRecentOvertimes(User $user, int $limit): array
    {
        return Overtime::query()
            ->with('approver:id,full_name')
            ->where('user_id', $user->id)
            ->orderByDesc('overtime_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Overtime $overtime): array => [
                'id' => $overtime->id,
                'overtime_date' => $overtime->overtime_date,
                'planned_start' => $overtime->planned_start,
                'planned_end' => $overtime->planned_end,
                'reason' => $overtime->reason,
                'approval_status' => $overtime->approval_status,
                'approved_by' => $overtime->approver?->full_name,
                'actual_start' => $overtime->actual_start,
                'actual_end' => $overtime->actual_end,
            ])
            ->all();
    }

    private function validatePresencePayload(Request $request): array
    {
        return $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
        ]);
    }

    private function getAttendanceSetting(): Setting
    {
        $setting = Setting::query()->latest('id')->first();

        if (! $setting || blank($setting->latitude) || blank($setting->longitude)) {
            throw ValidationException::withMessages([
                'setting' => 'Lokasi kantor belum dikonfigurasi admin, jadi presensi belum bisa digunakan.',
            ]);
        }

        return $setting;
    }

    private function assertWithinOfficeRadius(float $latitude, float $longitude, Setting $setting): void
    {
        $officeLatitude = (float) $setting->latitude;
        $officeLongitude = (float) $setting->longitude;
        $distance = Geo::distanceInMeters($officeLatitude, $officeLongitude, $latitude, $longitude);

        if ($distance > (float) $setting->radius_meters) {
            throw ValidationException::withMessages([
                'location' => 'Lokasi Anda berada di luar radius kantor. Presensi ditolak.',
            ]);
        }
    }

    private function assertCheckInTimeWindow(Setting $setting): void
    {
        if (blank($setting->check_in_time)) {
            throw ValidationException::withMessages([
                'attendance' => 'Jam masuk belum dikonfigurasi admin.',
            ]);
        }

        $now = now();
        $checkInAt = Carbon::parse($now->toDateString().' '.$setting->check_in_time);
        $checkInDeadline = $checkInAt->copy()->addMinutes($setting->checkInMaxLateMinutes());

        if ($now->lt($checkInAt)) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen masuk baru bisa dilakukan mulai pukul '.$checkInAt->format('H:i').' WITA.',
            ]);
        }

        if ($now->gt($checkInDeadline)) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen masuk ditutup pukul '.$checkInDeadline->format('H:i').' WITA.',
            ]);
        }
    }

    private function assertCheckOutTimeReached(Setting $setting): void
    {
        if (blank($setting->check_out_time)) {
            throw ValidationException::withMessages([
                'attendance' => 'Jam pulang belum dikonfigurasi admin.',
            ]);
        }

        $now = now();
        $checkOutAt = Carbon::parse($now->toDateString().' '.$setting->check_out_time);

        if ($now->lt($checkOutAt)) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen pulang baru bisa dilakukan mulai pukul '.$checkOutAt->format('H:i').' WITA.',
            ]);
        }
    }

    private function assertOvertimeTimeReached(Overtime $overtime, string $field, string $label): void
    {
        $plannedTime = $overtime->{$field};

        if (blank($plannedTime)) {
            throw ValidationException::withMessages([
                'overtime' => 'Jam '.$label.' lembur belum tersedia pada pengajuan ini.',
            ]);
        }

        $scheduledAt = Carbon::parse($overtime->overtime_date.' '.$plannedTime);

        if (now()->lt($scheduledAt)) {
            throw ValidationException::withMessages([
                'overtime' => 'Absen '.$label.' lembur baru bisa dilakukan mulai pukul '.$scheduledAt->format('H:i').' WITA sesuai jam yang diajukan.',
            ]);
        }
    }

    private function assertOvertimeOwnership(Overtime $overtime, int $userId): void
    {
        abort_unless($overtime->user_id === $userId, 404);
    }
}
