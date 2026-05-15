<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\PublicFileUrl;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = (string) $request->string('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->string('date_to', Carbon::now()->toDateString());
        $status = (string) $request->string('status', 'all');
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        $query = LeaveRequest::query()
            ->with([
                'user:id,full_name,id_number',
                'approver:id,full_name',
            ])
            ->whereBetween('leave_date', [$dateFrom, $dateTo])
            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'Employee'));

        if ($status !== 'all') {
            $query->where('approval_status', $status);
        }

        if ($employeeId) {
            $query->where('user_id', $employeeId);
        }

        $leaveRequests = (clone $query)
            ->orderByDesc('leave_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Leaves', [
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
            'leaveRequests' => $leaveRequests->through(fn (LeaveRequest $leaveRequest): array => [
                'id' => $leaveRequest->id,
                'leave_date' => $leaveRequest->leave_date,
                'leave_type' => $leaveRequest->leave_type,
                'employee_name' => $leaveRequest->user?->full_name ?? '-',
                'id_number' => $leaveRequest->user?->id_number,
                'reason' => $leaveRequest->reason,
                'proof_photo' => PublicFileUrl::make($leaveRequest->proof_photo),
                'proof_photo_download_url' => route('admin.leaves.proof.download', $leaveRequest, false),
                'approval_status' => $leaveRequest->approval_status,
                'approved_by' => $leaveRequest->approver?->full_name,
            ]),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        if ($leaveRequest->approval_status !== 'Pending') {
            return back()->with('error', 'Pengajuan izin sudah diproses sebelumnya.');
        }

        $leaveRequest->update([
            'approval_status' => 'Approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Pengajuan izin berhasil disetujui.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        if ($leaveRequest->approval_status !== 'Pending') {
            return back()->with('error', 'Pengajuan izin sudah diproses sebelumnya.');
        }

        $leaveRequest->update([
            'approval_status' => 'Rejected',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Pengajuan izin berhasil ditolak.');
    }

    public function downloadProof(LeaveRequest $leaveRequest): StreamedResponse
    {
        abort_unless($leaveRequest->user?->role === 'Employee', 404);
        abort_if(blank($leaveRequest->proof_photo), 404);
        abort_unless(Storage::disk('public')->exists($leaveRequest->proof_photo), 404);

        $extension = pathinfo($leaveRequest->proof_photo, PATHINFO_EXTENSION) ?: 'jpg';
        $employeeName = Str::slug($leaveRequest->user?->full_name ?? 'karyawan');
        $filename = sprintf('bukti-izin-%s-%s.%s', $employeeName, $leaveRequest->leave_date, $extension);

        return Storage::disk('public')->download($leaveRequest->proof_photo, $filename);
    }
}
