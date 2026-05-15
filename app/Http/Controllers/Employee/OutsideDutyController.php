<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\OutsideDuty;
use App\Support\CapturedPhoto;
use App\Support\Geo;
use App\Support\PublicFileUrl;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OutsideDutyController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->role === 'Admin') {
            return redirect()->route('dashboard');
        }

        $outsideDuties = OutsideDuty::query()
            ->with('approver:id,full_name')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('duty_date')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Employee/OutsideDuties', [
            'outsideDuties' => $outsideDuties->through(fn (OutsideDuty $outsideDuty): array => $this->serializeOutsideDuty($outsideDuty)),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->role === 'Admin') {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Employee/OutsideDutyCreate');
    }

    public function attendance(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->role === 'Admin') {
            return redirect()->route('dashboard');
        }

        $selectedOutsideDutyId = $request->filled('outside_duty_id') ? (int) $request->input('outside_duty_id') : null;

        return Inertia::render('Employee/OutsideDutyAttendance', [
            'approvedTodayOutsideDuties' => $selectedOutsideDutyId ? OutsideDuty::query()
                ->with('approver:id,full_name')
                ->where('user_id', $request->user()->id)
                ->whereDate('duty_date', Carbon::today()->toDateString())
                ->where('approval_status', 'Approved')
                ->whereKey($selectedOutsideDutyId)
                ->orderBy('planned_start')
                ->get()
                ->map(fn (OutsideDuty $outsideDuty): array => $this->serializeOutsideDuty($outsideDuty))
                ->all() : [],
            'selectedOutsideDutyId' => $selectedOutsideDutyId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'Employee', 403);

        $validated = $request->validate([
            'duty_date' => ['required', 'date', 'after_or_equal:today'],
            'planned_start' => ['required', 'date_format:H:i'],
            'planned_end' => ['required', 'date_format:H:i'],
            'location_name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'requested_radius_meters' => ['required', 'integer', 'min:1', 'max:50000'],
            'reason' => ['required', 'string', 'max:1000'],
            'request_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($validated['planned_end'] <= $validated['planned_start']) {
            throw ValidationException::withMessages([
                'planned_end' => 'Jam selesai harus setelah jam mulai pada hari yang sama.',
            ]);
        }

        OutsideDuty::query()->create([
            'user_id' => $request->user()->id,
            'duty_date' => Carbon::parse($validated['duty_date'])->toDateString(),
            'planned_start' => $validated['planned_start'],
            'planned_end' => $validated['planned_end'],
            'location_name' => $validated['location_name'],
            'latitude' => (string) $validated['latitude'],
            'longitude' => (string) $validated['longitude'],
            'requested_radius_meters' => $validated['requested_radius_meters'],
            'approved_radius_meters' => null,
            'reason' => $validated['reason'],
            'request_photo' => $request->file('request_photo')->store('outside-duties/request', 'public'),
            'approval_status' => 'Pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Pengajuan tugas luar berhasil dikirim dan menunggu approval admin.');
    }

    public function attend(Request $request, OutsideDuty $outsideDuty): RedirectResponse
    {
        abort_unless($request->user()?->role === 'Employee', 403);
        $this->assertOutsideDutyOwnership($outsideDuty, $request->user()->id);

        $payload = $this->validatePresencePayload($request);

        if ($outsideDuty->approval_status !== 'Approved') {
            throw ValidationException::withMessages([
                'outside_duty' => 'Absen tugas luar hanya tersedia untuk pengajuan yang sudah disetujui admin.',
            ]);
        }

        if ($outsideDuty->duty_date !== Carbon::today()->toDateString()) {
            throw ValidationException::withMessages([
                'outside_duty' => 'Absen tugas luar hanya bisa dilakukan pada tanggal tugas yang disetujui.',
            ]);
        }

        if ($outsideDuty->attended_at) {
            throw ValidationException::withMessages([
                'outside_duty' => 'Anda sudah melakukan absen tugas luar untuk pengajuan ini.',
            ]);
        }

        $this->assertOutsideDutyTimeWindow($outsideDuty);
        $this->assertWithinApprovedRadius($outsideDuty, $payload['latitude'], $payload['longitude']);

        $outsideDuty->update([
            'attended_at' => now(),
            'attendance_photo' => CapturedPhoto::storeDataUrl($payload['photo'], 'outside-duties/attendance', 'Foto absen tugas luar wajib diambil langsung dari kamera.'),
            'attendance_location' => Geo::formatLocation($payload['latitude'], $payload['longitude']),
        ]);

        return back()->with('success', 'Absen tugas luar berhasil disimpan.');
    }

    private function serializeOutsideDuty(OutsideDuty $outsideDuty): array
    {
        return [
            'id' => $outsideDuty->id,
            'duty_date' => $outsideDuty->duty_date,
            'planned_start' => $outsideDuty->planned_start,
            'planned_end' => $outsideDuty->planned_end,
            'location_name' => $outsideDuty->location_name,
            'latitude' => $outsideDuty->latitude,
            'longitude' => $outsideDuty->longitude,
            'requested_radius_meters' => $outsideDuty->requested_radius_meters,
            'approved_radius_meters' => $outsideDuty->approved_radius_meters,
            'effective_radius_meters' => $outsideDuty->effectiveRadiusMeters(),
            'reason' => $outsideDuty->reason,
            'request_photo' => PublicFileUrl::make($outsideDuty->request_photo),
            'approval_status' => $outsideDuty->approval_status,
            'approved_by' => $outsideDuty->approver?->full_name,
            'approved_at' => optional($outsideDuty->approved_at)->toDateTimeString(),
            'rejection_reason' => $outsideDuty->rejection_reason,
            'attended_at' => optional($outsideDuty->attended_at)->toDateTimeString(),
            'attendance_photo' => PublicFileUrl::make($outsideDuty->attendance_photo),
            'attendance_location' => $outsideDuty->attendance_location,
            'created_at' => optional($outsideDuty->created_at)->toDateTimeString(),
        ];
    }

    private function assertOutsideDutyOwnership(OutsideDuty $outsideDuty, int $userId): void
    {
        abort_unless($outsideDuty->user_id === $userId, 404);
    }

    private function validatePresencePayload(Request $request): array
    {
        return $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
        ]);
    }

    private function assertWithinApprovedRadius(OutsideDuty $outsideDuty, float $latitude, float $longitude): void
    {
        if (blank($outsideDuty->approved_radius_meters)) {
            throw ValidationException::withMessages([
                'location' => 'Radius tugas luar belum disetujui admin.',
            ]);
        }

        $distance = Geo::distanceInMeters(
            (float) $outsideDuty->latitude,
            (float) $outsideDuty->longitude,
            $latitude,
            $longitude,
        );

        if ($distance > (float) $outsideDuty->approved_radius_meters) {
            throw ValidationException::withMessages([
                'location' => 'Lokasi Anda berada di luar radius tugas luar yang disetujui. Absen tugas luar ditolak.',
            ]);
        }
    }

    private function assertOutsideDutyTimeWindow(OutsideDuty $outsideDuty): void
    {
        if (blank($outsideDuty->planned_start) || blank($outsideDuty->planned_end)) {
            throw ValidationException::withMessages([
                'outside_duty' => 'Jam tugas luar belum tersedia pada pengajuan ini.',
            ]);
        }

        $startsAt = Carbon::parse($outsideDuty->duty_date.' '.$outsideDuty->planned_start);
        $endsAt = Carbon::parse($outsideDuty->duty_date.' '.$outsideDuty->planned_end);
        $now = now();

        if ($now->lt($startsAt)) {
            throw ValidationException::withMessages([
                'outside_duty' => 'Absen tugas luar baru bisa dilakukan mulai pukul '.$startsAt->format('H:i').' WITA sesuai jam yang disetujui.',
            ]);
        }

        if ($now->gt($endsAt)) {
            throw ValidationException::withMessages([
                'outside_duty' => 'Absen tugas luar sudah ditutup pukul '.$endsAt->format('H:i').' WITA sesuai jam yang disetujui.',
            ]);
        }
    }
}
