<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\OutsideDuty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_employees(): void
    {
        $admin = $this->createAdmin();

        $indexResponse = $this->actingAs($admin)->get(route('admin.employees.index'));
        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn (Assert $page) => $page->component('Admin/Employees'));

        $storeResponse = $this->actingAs($admin)->post(route('admin.employees.store'), [
            'id_number' => '9988776655443322',
            'full_name' => 'Karyawan Baru',
            'email' => 'karyawan.baru@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $storeResponse->assertRedirect();

        $employee = User::query()->where('email', 'karyawan.baru@example.com')->firstOrFail();
        $this->assertSame('Employee', $employee->role);

        $updateResponse = $this->actingAs($admin)->patch(route('admin.employees.update', $employee), [
            'id_number' => '9988776655443322',
            'full_name' => 'Karyawan Update',
            'email' => 'karyawan.update@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'full_name' => 'Karyawan Update',
            'email' => 'karyawan.update@example.com',
            'role' => 'Employee',
        ]);

        $deleteResponse = $this->actingAs($admin)->delete(route('admin.employees.destroy', $employee));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
    }

    public function test_admin_can_manage_setting_monitoring_and_reports(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee();

        Attendance::query()->create([
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'clock_in_at' => '08:30:00',
            'clock_in_photo' => 'clock-in.jpg',
            'clock_in_location' => '-6.20,106.81',
            'clock_out_at' => '17:05:00',
            'clock_out_photo' => 'clock-out.jpg',
            'clock_out_location' => '-6.20,106.81',
        ]);

        $overtime = Overtime::query()->create([
            'user_id' => $employee->id,
            'overtime_date' => now()->toDateString(),
            'planned_start' => '18:00:00',
            'planned_end' => '20:00:00',
            'reason' => 'Penyelesaian laporan',
            'approval_status' => 'Pending',
            'approved_by' => null,
        ]);

        $outsideDuty = OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '09:00:00',
            'planned_end' => '12:00:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => '-6.250000',
            'longitude' => '106.850000',
            'requested_radius_meters' => 150,
            'reason' => 'Inspeksi area tugas luar.',
            'request_photo' => 'outside-duty.jpg',
            'approval_status' => 'Pending',
            'approved_by' => null,
        ]);

        $settingResponse = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'latitude' => '-6.200000',
            'longitude' => '106.816666',
            'radius_meters' => 120,
            'check_in_time' => '08:00',
            'check_in_late_tolerance_minutes' => 20,
            'check_in_max_late_minutes' => 40,
            'check_out_time' => '17:00',
        ]);
        $settingResponse->assertRedirect();
        $this->assertDatabaseHas('settings', [
            'radius_meters' => 120,
            'check_in_late_tolerance_minutes' => 20,
            'check_in_max_late_minutes' => 40,
        ]);

        $attendanceResponse = $this->actingAs($admin)->get(route('admin.attendances.index'));
        $attendanceResponse->assertOk();
        $attendanceResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Attendances')
            ->where('summary.lateCheckIn', 1)
            ->where('attendances.data.0.clock_in_status', 'Lambat'));

        $overtimeResponse = $this->actingAs($admin)->get(route('admin.overtimes.index'));
        $overtimeResponse->assertOk();
        $overtimeResponse->assertInertia(fn (Assert $page) => $page->component('Admin/Overtimes'));

        $outsideDutyResponse = $this->actingAs($admin)->get(route('admin.outside-duties.index'));
        $outsideDutyResponse->assertOk();
        $outsideDutyResponse->assertInertia(fn (Assert $page) => $page->component('Admin/OutsideDuties'));

        $approveResponse = $this->actingAs($admin)->patch(route('admin.overtimes.approve', $overtime));
        $approveResponse->assertRedirect();
        $this->assertDatabaseHas('overtimes', [
            'id' => $overtime->id,
            'approval_status' => 'Approved',
            'approved_by' => $admin->id,
        ]);

        $approveOutsideDutyResponse = $this->actingAs($admin)->patch(route('admin.outside-duties.approve', $outsideDuty), [
            'approved_radius_meters' => 180,
        ]);
        $approveOutsideDutyResponse->assertRedirect();
        $this->assertDatabaseHas('outside_duties', [
            'id' => $outsideDuty->id,
            'approval_status' => 'Approved',
            'approved_by' => $admin->id,
            'approved_radius_meters' => 180,
        ]);

        $reportResponse = $this->actingAs($admin)->get(route('admin.reports.index'));
        $reportResponse->assertOk();
        $reportResponse->assertInertia(fn (Assert $page) => $page->component('Admin/Reports'));

        $csvAttendanceResponse = $this->actingAs($admin)->get(route('admin.reports.attendance.csv'));
        $csvAttendanceResponse->assertOk();
        $csvAttendanceResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csvOvertimeResponse = $this->actingAs($admin)->get(route('admin.reports.overtime.csv'));
        $csvOvertimeResponse->assertOk();
        $csvOvertimeResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_employee_cannot_access_admin_module_pages(): void
    {
        $employee = $this->createEmployee();

        $this->actingAs($employee)->get(route('admin.employees.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.attendances.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.overtimes.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.outside-duties.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.reports.index'))->assertForbidden();
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'id_number' => '1000000000000001',
            'full_name' => 'Admin Test',
            'email' => 'admin.test@example.com',
            'password' => Hash::make('password'),
            'role' => 'Admin',
        ]);
    }

    private function createEmployee(): User
    {
        return User::query()->create([
            'id_number' => '1000000000000002',
            'full_name' => 'Employee Test',
            'email' => 'employee.test@example.com',
            'password' => Hash::make('password'),
            'role' => 'Employee',
        ]);
    }
}
