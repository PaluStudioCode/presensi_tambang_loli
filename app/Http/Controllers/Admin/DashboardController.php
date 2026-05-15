<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicFileUrl;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $setting = Setting::query()->latest('id')->first();

        $attendanceToday = Attendance::query()->whereDate('date', $today->toDateString());
        $attendanceTodayCount = (clone $attendanceToday)->count();
        $clockedInToday = (clone $attendanceToday)->whereNotNull('clock_in_at')->count();
        $clockedOutToday = (clone $attendanceToday)->whereNotNull('clock_out_at')->count();

        $lateCheckInToday = 0;
        $lateThreshold = $this->resolveLateThreshold($setting);
        if ($lateThreshold) {
            $lateCheckInToday = (clone $attendanceToday)
                ->whereNotNull('clock_in_at')
                ->where('clock_in_at', '>', $lateThreshold)
                ->count();
        }

        $overtimeStatusThisMonth = Overtime::query()
            ->whereBetween('overtime_date', [$monthStart, $monthEnd])
            ->selectRaw('approval_status, COUNT(*) as total')
            ->groupBy('approval_status')
            ->pluck('total', 'approval_status');

        $approvedHoursThisMonth = Overtime::query()
            ->where('approval_status', 'Approved')
            ->whereBetween('overtime_date', [$monthStart, $monthEnd])
            ->whereNotNull('actual_start')
            ->whereNotNull('actual_end')
            ->get(['actual_start', 'actual_end'])
            ->sum(function (Overtime $overtime): float {
                $start = Carbon::createFromFormat('H:i:s', $overtime->actual_start);
                $end = Carbon::createFromFormat('H:i:s', $overtime->actual_end);

                if ($end->lessThan($start)) {
                    $end->addDay();
                }

                return $start->diffInMinutes($end) / 60;
            });

        $recentAttendances = Attendance::query()
            ->with('user:id,full_name,id_number')
            ->orderByDesc('date')
            ->orderByDesc('clock_in_at')
            ->limit(10)
            ->get();

        $pendingOvertimeRequests = Overtime::query()
            ->with('user:id,full_name,id_number')
            ->where('approval_status', 'Pending')
            ->orderBy('overtime_date')
            ->orderBy('planned_start')
            ->limit(10)
            ->get();

        $recentOvertimes = Overtime::query()
            ->with([
                'user:id,full_name,id_number',
                'approver:id,full_name',
            ])
            ->orderByDesc('overtime_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $totalEmployees = User::query()->where('role', 'Employee')->count();
        $presentEmployeesToday = Attendance::query()
            ->whereDate('date', $today->toDateString())
            ->distinct()
            ->count('user_id');
        $pendingOvertimeCount = Overtime::query()->where('approval_status', 'Pending')->count();

        return Inertia::render('Admin/Dashboard', [
            'summary' => [
                'totalEmployees' => $totalEmployees,
                'presentEmployeesToday' => $presentEmployeesToday,
                'attendanceToday' => $attendanceTodayCount,
                'pendingOvertimes' => $pendingOvertimeCount,
                'approvedOvertimesThisMonth' => (int) ($overtimeStatusThisMonth['Approved'] ?? 0),
                'rejectedOvertimesThisMonth' => (int) ($overtimeStatusThisMonth['Rejected'] ?? 0),
                'approvedOvertimeHoursThisMonth' => round($approvedHoursThisMonth, 1),
            ],
            'attendanceToday' => [
                'clockedIn' => $clockedInToday,
                'clockedOut' => $clockedOutToday,
                'lateCheckIn' => $lateCheckInToday,
            ],
            'setting' => $setting ? [
                'latitude' => $setting->latitude,
                'longitude' => $setting->longitude,
                'radius_meters' => $setting->radius_meters,
                'check_in_time' => $setting->check_in_time,
                'check_in_late_tolerance_minutes' => $setting->checkInLateToleranceMinutes(),
                'check_in_max_late_minutes' => $setting->checkInMaxLateMinutes(),
                'check_out_time' => $setting->check_out_time,
            ] : null,
            'recentAttendances' => $recentAttendances->map(function (Attendance $attendance) use ($setting): array {
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
            'pendingOvertimes' => $pendingOvertimeRequests->map(function (Overtime $overtime): array {
                return [
                    'id' => $overtime->id,
                    'overtime_date' => $overtime->overtime_date,
                    'employee_name' => $overtime->user?->full_name ?? '-',
                    'id_number' => $overtime->user?->id_number,
                    'planned_start' => $overtime->planned_start,
                    'planned_end' => $overtime->planned_end,
                    'reason' => $overtime->reason,
                    'overtime_request_photo' => PublicFileUrl::make($overtime->overtime_request_photo),
                ];
            }),
            'recentOvertimes' => $recentOvertimes->map(function (Overtime $overtime): array {
                return [
                    'id' => $overtime->id,
                    'overtime_date' => $overtime->overtime_date,
                    'employee_name' => $overtime->user?->full_name ?? '-',
                    'approval_status' => $overtime->approval_status,
                    'planned_start' => $overtime->planned_start,
                    'planned_end' => $overtime->planned_end,
                    'actual_start' => $overtime->actual_start,
                    'actual_end' => $overtime->actual_end,
                    'overtime_request_photo' => PublicFileUrl::make($overtime->overtime_request_photo),
                    'approved_by' => $overtime->approver?->full_name,
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
