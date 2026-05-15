<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutsideDuty;
use App\Models\User;
use App\Support\PublicFileUrl;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutsideDutyController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = (string) $request->string('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->string('date_to', Carbon::now()->toDateString());
        $status = (string) $request->string('status', 'all');
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $query = OutsideDuty::query()
            ->with([
                'user:id,full_name,id_number',
                'approver:id,full_name',
            ])
            ->whereBetween('duty_date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'));

        if ($status !== 'all') {
            $query->where('approval_status', $status);
        }

        if ($employeeId) {
            $query->where('user_id', $employeeId);
        }

        $outsideDuties = (clone $query)
            ->orderByDesc('duty_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/OutsideDuties', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
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
                'totalRequests' => (clone $query)->count(),
                'pendingRequests' => (clone $query)->where('approval_status', 'Pending')->count(),
                'approvedRequests' => (clone $query)->where('approval_status', 'Approved')->count(),
                'rejectedRequests' => (clone $query)->where('approval_status', 'Rejected')->count(),
            ],
            'outsideDuties' => $outsideDuties->through(fn (OutsideDuty $outsideDuty): array => $this->serializeOutsideDuty($outsideDuty)),
        ]);
    }

    public function approve(Request $request, OutsideDuty $outsideDuty): RedirectResponse
    {
        if ($outsideDuty->approval_status !== 'Pending') {
            return back()->with('error', 'Pengajuan tugas luar sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'approved_radius_meters' => ['nullable', 'integer', 'min:1', 'max:50000'],
        ]);

        $outsideDuty->update([
            'approval_status' => 'Approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approved_radius_meters' => $validated['approved_radius_meters'] ?? $outsideDuty->requested_radius_meters,
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Pengajuan tugas luar berhasil disetujui.');
    }

    public function reject(Request $request, OutsideDuty $outsideDuty): RedirectResponse
    {
        if ($outsideDuty->approval_status !== 'Pending') {
            return back()->with('error', 'Pengajuan tugas luar sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $outsideDuty->update([
            'approval_status' => 'Rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => null,
            'approved_radius_meters' => null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return back()->with('success', 'Pengajuan tugas luar berhasil ditolak.');
    }

    private function serializeOutsideDuty(OutsideDuty $outsideDuty): array
    {
        return [
            'id' => $outsideDuty->id,
            'duty_date' => $outsideDuty->duty_date,
            'employee_name' => $outsideDuty->user?->full_name ?? '-',
            'id_number' => $outsideDuty->user?->id_number,
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
}
