<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicFileUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = (string) $request->string('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->string('date_to', Carbon::now()->toDateString());
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $query = Attendance::query()
            ->with('user:id,full_name,id_number')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'));

        if ($employeeId) {
            $query->where('user_id', $employeeId);
        }

        $setting = Setting::query()->latest('id')->first();

        $totalRecords = (clone $query)->count();
        $clockedIn = (clone $query)->whereNotNull('clock_in_at')->count();
        $clockedOut = (clone $query)->whereNotNull('clock_out_at')->count();

        $lateCheckIn = 0;
        $lateThreshold = $this->resolveLateThreshold($setting);
        if ($lateThreshold) {
            $lateCheckIn = (clone $query)
                ->whereNotNull('clock_in_at')
                ->where('clock_in_at', '>', $lateThreshold)
                ->count();
        }

        $attendances = (clone $query)
            ->orderByDesc('date')
            ->orderByDesc('clock_in_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Attendances', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'employee_id' => $employeeId,
            ],
            'employees' => User::query()
                ->where('role', 'Employee')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'id_number'])
                ->map(fn (User $employee): array => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                    'id_number' => $employee->id_number,
                ]),
            'summary' => [
                'totalRecords' => $totalRecords,
                'clockedIn' => $clockedIn,
                'clockedOut' => $clockedOut,
                'lateCheckIn' => $lateCheckIn,
            ],
            'attendances' => $attendances->through(function (Attendance $attendance) use ($setting): array {
                return [
                    'id' => $attendance->id,
                    'date' => $attendance->date,
                    'employee_name' => $attendance->user?->full_name ?? '-',
                    'id_number' => $attendance->user?->id_number,
                    'clock_in_at' => $attendance->clock_in_at,
                    'clock_in_status' => $this->resolveClockInStatus($attendance->clock_in_at, $setting),
                    'clock_out_at' => $attendance->clock_out_at,
                    'clock_in_photo' => PublicFileUrl::make($attendance->clock_in_photo),
                    'clock_out_photo' => PublicFileUrl::make($attendance->clock_out_photo),
                    'clock_in_location' => $attendance->clock_in_location,
                    'clock_out_location' => $attendance->clock_out_location,
                ];
            }),
        ]);
    }

    private function resolveLateThreshold(?Setting $setting): ?string
    {
        if (! $setting?->check_in_time) {
            return null;
        }

        return Carbon::parse('2000-01-01 '.$setting->check_in_time)
            ->addMinutes($setting->checkInLateToleranceMinutes())
            ->format('H:i:s');
    }

    private function resolveClockInStatus(?string $clockInAt, ?Setting $setting): string
    {
        if (! $clockInAt) {
            return '-';
        }

        $lateThreshold = $this->resolveLateThreshold($setting);

        if (! $lateThreshold) {
            return 'Tercatat';
        }

        return $clockInAt > $lateThreshold ? 'Lambat' : 'Tepat Waktu';
    }
}
