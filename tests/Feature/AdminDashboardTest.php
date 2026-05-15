<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicFileUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_dashboard_and_see_summary_data(): void
    {
        Storage::fake('public');

        $admin = $this->createUser('Admin');
        $employee = $this->createUser('Employee', '1000000002', 'Budi Karyawan', 'budi@example.com');

        Setting::query()->create([
            'latitude' => '-6.200000',
            'longitude' => '106.816666',
            'radius_meters' => 100,
            'check_in_time' => '08:00:00',
            'check_in_late_tolerance_minutes' => 20,
            'check_in_max_late_minutes' => 40,
            'check_out_time' => '17:00:00',
        ]);

        Attendance::query()->create([
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'clock_in_at' => '08:25:00',
            'clock_in_photo' => 'clock-in.jpg',
            'clock_in_location' => '-6.2,106.8',
            'clock_out_at' => null,
            'clock_out_photo' => null,
            'clock_out_location' => null,
        ]);

        Overtime::query()->create([
            'user_id' => $employee->id,
            'overtime_date' => now()->toDateString(),
            'planned_start' => '18:00:00',
            'planned_end' => '20:00:00',
            'reason' => 'Closing laporan harian',
            'approval_status' => 'Pending',
            'approved_by' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('summary.totalEmployees', 1)
            ->where('summary.presentEmployeesToday', 1)
            ->where('summary.pendingOvertimes', 1)
            ->where('attendanceToday.clockedIn', 1)
            ->where('attendanceToday.clockedOut', 0)
            ->where('attendanceToday.lateCheckIn', 1)
            ->where('recentAttendances.0.clock_in_status', 'Lambat')
            ->where('recentAttendances.0.clock_in_photo', PublicFileUrl::make('clock-in.jpg'))
            ->has('pendingOvertimes', 1)
            ->has('recentAttendances', 1));
    }

    public function test_authenticated_user_can_open_public_file_route(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('attendance/clock-in/test.jpg', 'fake-image');

        $admin = $this->createUser('Admin', '1000000009', 'Admin Media', 'admin.media@example.com');

        $response = $this->actingAs($admin)->get(route('public-files.show', [
            'path' => 'attendance/clock-in/test.jpg',
        ], false));

        $response->assertOk();
    }

    public function test_employee_is_forbidden_to_access_admin_dashboard(): void
    {
        $employee = $this->createUser('Employee');

        $response = $this->actingAs($employee)->get(route('dashboard'));

        $response->assertForbidden();
    }

    public function test_admin_can_approve_and_reject_pending_overtime(): void
    {
        $admin = $this->createUser('Admin');
        $employee = $this->createUser('Employee', '1000000004', 'Sari Karyawan', 'sari@example.com');

        $overtimeToApprove = Overtime::query()->create([
            'user_id' => $employee->id,
            'overtime_date' => now()->toDateString(),
            'planned_start' => '18:00:00',
            'planned_end' => '20:00:00',
            'reason' => 'Approval test',
            'approval_status' => 'Pending',
            'approved_by' => null,
        ]);

        $overtimeToReject = Overtime::query()->create([
            'user_id' => $employee->id,
            'overtime_date' => now()->toDateString(),
            'planned_start' => '19:00:00',
            'planned_end' => '21:00:00',
            'reason' => 'Reject test',
            'approval_status' => 'Pending',
            'approved_by' => null,
        ]);

        $approveResponse = $this->actingAs($admin)->patch(route('admin.overtimes.approve', $overtimeToApprove));
        $approveResponse->assertRedirect();

        $this->assertDatabaseHas('overtimes', [
            'id' => $overtimeToApprove->id,
            'approval_status' => 'Approved',
            'approved_by' => $admin->id,
        ]);

        $rejectResponse = $this->actingAs($admin)->patch(route('admin.overtimes.reject', $overtimeToReject));
        $rejectResponse->assertRedirect();

        $this->assertDatabaseHas('overtimes', [
            'id' => $overtimeToReject->id,
            'approval_status' => 'Rejected',
            'approved_by' => $admin->id,
        ]);
    }

    private function createUser(
        string $role,
        string $idNumber = '1000000001',
        string $fullName = 'Admin User',
        string $email = 'admin@test.local'
    ): User {
        return User::query()->create([
            'id_number' => $idNumber,
            'full_name' => $fullName,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
