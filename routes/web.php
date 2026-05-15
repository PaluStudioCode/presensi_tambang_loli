<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmployeeController as AdminEmployeeController;
use App\Http\Controllers\Admin\LeaveRequestController as AdminLeaveRequestController;
use App\Http\Controllers\Admin\OvertimeController as AdminOvertimeController;
use App\Http\Controllers\Admin\OutsideDutyController as AdminOutsideDutyController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Employee\HomeController as EmployeeHomeController;
use App\Http\Controllers\Employee\LeaveRequestController as EmployeeLeaveRequestController;
use App\Http\Controllers\Employee\OutsideDutyController as EmployeeOutsideDutyController;
use App\Http\Controllers\PublicFileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/', function (Request $request) {
    if ($request->user()) {
        return redirect(match ($request->user()->role) {
            'Admin' => route('dashboard'),
            'Employee' => route('home'),
            default => route('dashboard'),
        });
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/admin/employees', [AdminEmployeeController::class, 'index'])->name('admin.employees.index');
    Route::post('/admin/employees', [AdminEmployeeController::class, 'store'])->name('admin.employees.store');
    Route::patch('/admin/employees/{employee}', [AdminEmployeeController::class, 'update'])->name('admin.employees.update');
    Route::delete('/admin/employees/{employee}', [AdminEmployeeController::class, 'destroy'])->name('admin.employees.destroy');

    Route::get('/admin/settings', [AdminSettingController::class, 'index'])->name('admin.settings.index');
    Route::put('/admin/settings', [AdminSettingController::class, 'update'])->name('admin.settings.update');

    Route::get('/admin/attendances', [AdminAttendanceController::class, 'index'])->name('admin.attendances.index');

    Route::get('/admin/overtimes', [AdminOvertimeController::class, 'index'])->name('admin.overtimes.index');
    Route::patch('/admin/overtimes/{overtime}/approve', [AdminOvertimeController::class, 'approve'])
        ->name('admin.overtimes.approve');
    Route::patch('/admin/overtimes/{overtime}/reject', [AdminOvertimeController::class, 'reject'])
        ->name('admin.overtimes.reject');

    Route::get('/admin/leaves', [AdminLeaveRequestController::class, 'index'])->name('admin.leaves.index');
    Route::get('/admin/leaves/{leaveRequest}/proof/download', [AdminLeaveRequestController::class, 'downloadProof'])
        ->name('admin.leaves.proof.download');
    Route::patch('/admin/leaves/{leaveRequest}/approve', [AdminLeaveRequestController::class, 'approve'])
        ->name('admin.leaves.approve');
    Route::patch('/admin/leaves/{leaveRequest}/reject', [AdminLeaveRequestController::class, 'reject'])
        ->name('admin.leaves.reject');

    Route::get('/admin/outside-duties', [AdminOutsideDutyController::class, 'index'])->name('admin.outside-duties.index');
    Route::patch('/admin/outside-duties/{outsideDuty}/approve', [AdminOutsideDutyController::class, 'approve'])
        ->name('admin.outside-duties.approve');
    Route::patch('/admin/outside-duties/{outsideDuty}/reject', [AdminOutsideDutyController::class, 'reject'])
        ->name('admin.outside-duties.reject');

    Route::get('/admin/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/reports/attendance.csv', [AdminReportController::class, 'attendanceCsv'])
        ->name('admin.reports.attendance.csv');
    Route::get('/admin/reports/overtime.csv', [AdminReportController::class, 'overtimeCsv'])
        ->name('admin.reports.overtime.csv');
    Route::get('/admin/reports/attendance.xls', [AdminReportController::class, 'attendanceExcel'])
        ->name('admin.reports.attendance.excel');
    Route::get('/admin/reports/overtime.xls', [AdminReportController::class, 'overtimeExcel'])
        ->name('admin.reports.overtime.excel');
});

Route::middleware('auth')->group(function () {
    Route::get('/files/public', [PublicFileController::class, 'show'])->name('public-files.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/home', [EmployeeHomeController::class, 'index'])->name('home');
    Route::get('/employee/attendance', [EmployeeHomeController::class, 'attendance'])
        ->name('employee.attendance.index');
    Route::get('/employee/overtimes', [EmployeeHomeController::class, 'overtimes'])
        ->name('employee.overtimes.index');
    Route::get('/employee/leaves', [EmployeeLeaveRequestController::class, 'index'])
        ->name('employee.leaves.index');
    Route::post('/employee/leaves', [EmployeeLeaveRequestController::class, 'store'])
        ->name('employee.leaves.store');
    Route::get('/employee/outside-duties', [EmployeeOutsideDutyController::class, 'index'])
        ->name('employee.outside-duties.index');
    Route::get('/employee/outside-duties/create', [EmployeeOutsideDutyController::class, 'create'])
        ->name('employee.outside-duties.create');
    Route::get('/employee/outside-duties/attendance', [EmployeeOutsideDutyController::class, 'attendance'])
        ->name('employee.outside-duties.attendance');
    Route::post('/employee/outside-duties', [EmployeeOutsideDutyController::class, 'store'])
        ->name('employee.outside-duties.store');
    Route::post('/employee/outside-duties/{outsideDuty}/attendance', [EmployeeOutsideDutyController::class, 'attend'])
        ->name('employee.outside-duties.attend');
    Route::post('/employee/attendance/clock-in', [EmployeeHomeController::class, 'clockIn'])
        ->name('employee.attendance.clock-in');
    Route::post('/employee/attendance/clock-out', [EmployeeHomeController::class, 'clockOut'])
        ->name('employee.attendance.clock-out');
    Route::post('/employee/overtimes', [EmployeeHomeController::class, 'storeOvertime'])
        ->name('employee.overtimes.store');
    Route::post('/employee/overtimes/{overtime}/start', [EmployeeHomeController::class, 'startOvertime'])
        ->name('employee.overtimes.start');
    Route::post('/employee/overtimes/{overtime}/finish', [EmployeeHomeController::class, 'finishOvertime'])
        ->name('employee.overtimes.finish');
});

require __DIR__.'/auth.php';
