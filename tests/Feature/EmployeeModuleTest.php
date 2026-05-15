<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Overtime;
use App\Models\OutsideDuty;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_can_access_dashboard_attendance_and_overtime_pages_then_handle_their_flows(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Setting::query()->create([
            'latitude' => '-6.200000',
            'longitude' => '106.816000',
            'radius_meters' => 150,
            'check_in_time' => '08:00:00',
            'check_out_time' => '17:00:00',
        ]);

        Carbon::setTestNow('2026-04-11 08:10:00');

        $homeResponse = $this->actingAs($employee)->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertInertia(fn (Assert $page) => $page->component('Employee/Home'));

        $attendancePageResponse = $this->actingAs($employee)->get(route('employee.attendance.index'));
        $attendancePageResponse->assertOk();
        $attendancePageResponse->assertInertia(fn (Assert $page) => $page->component('Employee/Attendance'));

        $overtimePageResponse = $this->actingAs($employee)->get(route('employee.overtimes.index'));
        $overtimePageResponse->assertOk();
        $overtimePageResponse->assertInertia(fn (Assert $page) => $page->component('Employee/Overtimes'));

        $outsideDutyPageResponse = $this->actingAs($employee)->get(route('employee.outside-duties.index'));
        $outsideDutyPageResponse->assertOk();
        $outsideDutyPageResponse->assertInertia(fn (Assert $page) => $page->component('Employee/OutsideDuties'));

        $outsideDutyCreateResponse = $this->actingAs($employee)->get(route('employee.outside-duties.create'));
        $outsideDutyCreateResponse->assertOk();
        $outsideDutyCreateResponse->assertInertia(fn (Assert $page) => $page->component('Employee/OutsideDutyCreate'));

        $outsideDutyAttendanceResponse = $this->actingAs($employee)->get(route('employee.outside-duties.attendance'));
        $outsideDutyAttendanceResponse->assertOk();
        $outsideDutyAttendanceResponse->assertInertia(fn (Assert $page) => $page->component('Employee/OutsideDutyAttendance'));

        $clockInResponse = $this->actingAs($employee)->post(route('employee.attendance.clock-in'), [
            'latitude' => -6.20001,
            'longitude' => 106.81601,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $clockInResponse->assertRedirect();

        $attendance = Attendance::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertNotNull($attendance->clock_in_at);
        $this->assertSame('-6.200010,106.816010', $attendance->clock_in_location);
        Storage::disk('public')->assertExists($attendance->clock_in_photo);

        Carbon::setTestNow('2026-04-11 17:05:00');

        $clockOutResponse = $this->actingAs($employee)->post(route('employee.attendance.clock-out'), [
            'latitude' => -6.20002,
            'longitude' => 106.81602,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $clockOutResponse->assertRedirect();

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out_at);
        $this->assertSame('-6.200020,106.816020', $attendance->clock_out_location);
        Storage::disk('public')->assertExists($attendance->clock_out_photo);

        $overtimeRequestResponse = $this->actingAs($employee)->post(route('employee.overtimes.store'), [
            'overtime_date' => now()->addDay()->toDateString(),
            'planned_start' => '18:00',
            'planned_end' => '20:00',
            'reason' => 'Closing laporan harian.',
            'request_photo' => $this->fakePhotoDataUrl(),
        ]);
        $overtimeRequestResponse->assertRedirect();

        $this->assertDatabaseHas('overtimes', [
            'user_id' => $employee->id,
            'approval_status' => 'Pending',
            'reason' => 'Closing laporan harian.',
        ]);

        $outsideDutyRequestResponse = $this->actingAs($employee)->post(route('employee.outside-duties.store'), [
            'duty_date' => now()->addDay()->toDateString(),
            'planned_start' => '09:00',
            'planned_end' => '12:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => -6.250000,
            'longitude' => 106.850000,
            'requested_radius_meters' => 120,
            'reason' => 'Inspeksi area tugas luar.',
            'request_photo' => UploadedFile::fake()->image('bukti-tugas-luar.jpg'),
        ]);
        $outsideDutyRequestResponse->assertRedirect();

        $outsideDuty = OutsideDuty::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('Pending', $outsideDuty->approval_status);
        Storage::disk('public')->assertExists($outsideDuty->request_photo);

        $approvedOvertime = Overtime::query()->create([
            'user_id' => $employee->id,
            'overtime_date' => now()->toDateString(),
            'planned_start' => '18:00:00',
            'planned_end' => '20:00:00',
            'reason' => 'Support operasional lapangan',
            'approval_status' => 'Approved',
        ]);

        Carbon::setTestNow('2026-04-11 18:05:00');

        $startResponse = $this->actingAs($employee)->post(route('employee.overtimes.start', $approvedOvertime), [
            'latitude' => -6.20003,
            'longitude' => 106.81603,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $startResponse->assertRedirect();

        $approvedOvertime->refresh();
        $this->assertNotNull($approvedOvertime->actual_start);
        Storage::disk('public')->assertExists($approvedOvertime->overtime_start_photo);

        Carbon::setTestNow('2026-04-11 19:00:00');

        $earlyFinishResponse = $this->actingAs($employee)->from(route('employee.overtimes.index'))->post(route('employee.overtimes.finish', $approvedOvertime), [
            'latitude' => -6.20004,
            'longitude' => 106.81604,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $earlyFinishResponse->assertRedirect(route('employee.overtimes.index'));
        $earlyFinishResponse->assertSessionHasErrors('overtime');

        $approvedOvertime->refresh();
        $this->assertNull($approvedOvertime->actual_end);

        Carbon::setTestNow('2026-04-11 20:05:00');

        $finishResponse = $this->actingAs($employee)->post(route('employee.overtimes.finish', $approvedOvertime), [
            'latitude' => -6.20004,
            'longitude' => 106.81604,
            'photo' => $this->fakePhotoDataUrl(),
        ]);
        $finishResponse->assertRedirect();

        $approvedOvertime->refresh();
        $this->assertNotNull($approvedOvertime->actual_end);
        Storage::disk('public')->assertExists($approvedOvertime->overtime_end_photo);
    }

    public function test_employee_attendance_is_rejected_when_outside_office_radius(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Setting::query()->create([
            'latitude' => '-6.200000',
            'longitude' => '106.816000',
            'radius_meters' => 100,
            'check_in_time' => '08:00:00',
        ]);

        Carbon::setTestNow('2026-04-11 08:10:00');

        OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '08:00:00',
            'planned_end' => '12:00:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => '-6.210000',
            'longitude' => '106.826000',
            'requested_radius_meters' => 1000,
            'approved_radius_meters' => 500,
            'reason' => 'Inspeksi alat berat',
            'request_photo' => 'outside-duty.jpg',
            'approval_status' => 'Approved',
        ]);

        $response = $this->actingAs($employee)->from(route('employee.attendance.index'))->post(route('employee.attendance.clock-in'), [
            'latitude' => -6.210000,
            'longitude' => 106.826000,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect(route('employee.attendance.index'));
        $response->assertSessionHasErrors('location');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_can_clock_in_late_until_maximum_late_limit(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Setting::query()->create([
            'latitude' => '-6.200000',
            'longitude' => '106.816000',
            'radius_meters' => 150,
            'check_in_time' => '08:00:00',
            'check_in_late_tolerance_minutes' => 20,
            'check_in_max_late_minutes' => 40,
        ]);

        Carbon::setTestNow('2026-04-11 08:30:00');

        $response = $this->actingAs($employee)->post(route('employee.attendance.clock-in'), [
            'latitude' => -6.20001,
            'longitude' => 106.81601,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => '2026-04-11',
            'clock_in_at' => '08:30:00',
        ]);
    }

    public function test_employee_cannot_clock_in_after_maximum_late_limit(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Setting::query()->create([
            'latitude' => '-6.200000',
            'longitude' => '106.816000',
            'radius_meters' => 150,
            'check_in_time' => '08:00:00',
            'check_in_late_tolerance_minutes' => 20,
            'check_in_max_late_minutes' => 40,
        ]);

        Carbon::setTestNow('2026-04-11 08:41:00');

        $response = $this->actingAs($employee)->from(route('employee.attendance.index'))->post(route('employee.attendance.clock-in'), [
            'latitude' => -6.20001,
            'longitude' => 106.81601,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect(route('employee.attendance.index'));
        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_can_attend_approved_outside_duty_with_photo_at_approved_location(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Carbon::setTestNow('2026-04-11 09:15:00');

        $outsideDuty = OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '08:00:00',
            'planned_end' => '12:00:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => '-6.250000',
            'longitude' => '106.850000',
            'requested_radius_meters' => 200,
            'approved_radius_meters' => 150,
            'reason' => 'Inspeksi alat berat',
            'request_photo' => 'outside-duty.jpg',
            'approval_status' => 'Approved',
        ]);

        $response = $this->actingAs($employee)->post(route('employee.outside-duties.attend', $outsideDuty), [
            'latitude' => -6.250010,
            'longitude' => 106.850010,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect();

        $outsideDuty->refresh();
        $this->assertNotNull($outsideDuty->attended_at);
        $this->assertSame('-6.250010,106.850010', $outsideDuty->attendance_location);
        Storage::disk('public')->assertExists($outsideDuty->attendance_photo);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_cannot_attend_outside_duty_before_scheduled_start_time(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Carbon::setTestNow('2026-04-11 07:59:00');

        $outsideDuty = OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '08:00:00',
            'planned_end' => '12:00:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => '-6.250000',
            'longitude' => '106.850000',
            'requested_radius_meters' => 200,
            'approved_radius_meters' => 150,
            'reason' => 'Inspeksi alat berat',
            'request_photo' => 'outside-duty.jpg',
            'approval_status' => 'Approved',
        ]);

        $response = $this->actingAs($employee)->from(route('employee.outside-duties.attendance'))->post(route('employee.outside-duties.attend', $outsideDuty), [
            'latitude' => -6.250010,
            'longitude' => 106.850010,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect(route('employee.outside-duties.attendance'));
        $response->assertSessionHasErrors('outside_duty');

        $outsideDuty->refresh();
        $this->assertNull($outsideDuty->attended_at);
        $this->assertNull($outsideDuty->attendance_photo);
    }

    public function test_employee_cannot_attend_outside_duty_after_scheduled_end_time(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Carbon::setTestNow('2026-04-11 12:01:00');

        $outsideDuty = OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '08:00:00',
            'planned_end' => '12:00:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => '-6.250000',
            'longitude' => '106.850000',
            'requested_radius_meters' => 200,
            'approved_radius_meters' => 150,
            'reason' => 'Inspeksi alat berat',
            'request_photo' => 'outside-duty.jpg',
            'approval_status' => 'Approved',
        ]);

        $response = $this->actingAs($employee)->from(route('employee.outside-duties.attendance'))->post(route('employee.outside-duties.attend', $outsideDuty), [
            'latitude' => -6.250010,
            'longitude' => 106.850010,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect(route('employee.outside-duties.attendance'));
        $response->assertSessionHasErrors('outside_duty');

        $outsideDuty->refresh();
        $this->assertNull($outsideDuty->attended_at);
        $this->assertNull($outsideDuty->attendance_photo);
    }

    public function test_employee_outside_duty_attendance_page_can_focus_selected_approved_request(): void
    {
        $employee = $this->createEmployee();

        Carbon::setTestNow('2026-04-11 09:15:00');

        OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '08:00:00',
            'planned_end' => '10:00:00',
            'location_name' => 'Site Loli Barat',
            'latitude' => '-6.240000',
            'longitude' => '106.840000',
            'requested_radius_meters' => 200,
            'approved_radius_meters' => 150,
            'reason' => 'Inspeksi awal',
            'request_photo' => 'outside-duty-1.jpg',
            'approval_status' => 'Approved',
        ]);

        $selectedOutsideDuty = OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '11:00:00',
            'planned_end' => '13:00:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => '-6.250000',
            'longitude' => '106.850000',
            'requested_radius_meters' => 200,
            'approved_radius_meters' => 150,
            'reason' => 'Inspeksi lanjutan',
            'request_photo' => 'outside-duty-2.jpg',
            'approval_status' => 'Approved',
        ]);

        $response = $this->actingAs($employee)->get(route('employee.outside-duties.attendance', [
            'outside_duty_id' => $selectedOutsideDuty->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Employee/OutsideDutyAttendance')
            ->where('selectedOutsideDutyId', $selectedOutsideDuty->id)
            ->has('approvedTodayOutsideDuties', 1)
            ->where('approvedTodayOutsideDuties.0.id', $selectedOutsideDuty->id));
    }

    public function test_outside_duty_attendance_uses_approved_radius_not_requested_radius(): void
    {
        Storage::fake('public');

        $employee = $this->createEmployee();

        Carbon::setTestNow('2026-04-11 09:15:00');

        $outsideDuty = OutsideDuty::query()->create([
            'user_id' => $employee->id,
            'duty_date' => now()->toDateString(),
            'planned_start' => '08:00:00',
            'planned_end' => '12:00:00',
            'location_name' => 'Site Loli Timur',
            'latitude' => '-6.250000',
            'longitude' => '106.850000',
            'requested_radius_meters' => 1000,
            'approved_radius_meters' => 50,
            'reason' => 'Inspeksi alat berat',
            'request_photo' => 'outside-duty.jpg',
            'approval_status' => 'Approved',
        ]);

        $response = $this->actingAs($employee)->from(route('employee.outside-duties.attendance'))->post(route('employee.outside-duties.attend', $outsideDuty), [
            'latitude' => -6.250700,
            'longitude' => 106.850000,
            'photo' => $this->fakePhotoDataUrl(),
        ]);

        $response->assertRedirect(route('employee.outside-duties.attendance'));
        $response->assertSessionHasErrors('location');
        $outsideDuty->refresh();
        $this->assertNull($outsideDuty->attended_at);
        $this->assertDatabaseCount('attendances', 0);
    }

    private function createEmployee(): User
    {
        return User::query()->create([
            'id_number' => '1000000000000099',
            'full_name' => 'Employee Test',
            'email' => 'employee.feature@example.com',
            'password' => Hash::make('password'),
            'role' => 'Employee',
        ]);
    }

    private function fakePhotoDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WnR2c8AAAAASUVORK5CYII=', true));
    }
}
