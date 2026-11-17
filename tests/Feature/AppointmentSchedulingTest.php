<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorScheduleRule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Appointments\ScheduleAvailability;
use App\Services\PersianDate;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppointmentSchedulingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function account(string $role = 'super-admin'): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->sole());

        return $user;
    }

    /** @return array{User, Doctor, Patient} */
    private function setupBooking(int $capacity = 1): array
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-21 07:00', 'Asia/Tehran'));
        $admin = $this->account();
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        DoctorScheduleRule::create(['doctor_id' => $doctor->id, 'weekday' => 0, 'local_start' => '09:00', 'local_end' => '12:00', 'effective_from' => '2027-03-01', 'slot_minutes' => 20, 'capacity' => $capacity, 'created_by' => $admin->id]);
        $this->actingAs($admin);

        return [$admin, $doctor, $patient];
    }

    /** @return array<string, mixed> */
    private function payload(Doctor $doctor, Patient $patient, array $changes = []): array
    {
        return array_replace(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'starts_at' => '2027-03-21T09:00', 'visit_type' => 'consultation'], $changes);
    }

    public function test_booking_persian_date_stores_utc_and_calendar_modes_render(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking();
        $this->post('/appointments', $this->payload($doctor, $patient, ['date_calendar' => 'persian', 'starts_at' => '۱۴۰۶/۰۱/۰۱ ۰۹:۰۰', 'status' => 'completed', 'created_by' => 999]))->assertSessionHasNoErrors()->assertRedirect();
        $appointment = Appointment::sole();
        $this->assertSame('2027-03-21 05:30', $appointment->starts_at->format('Y-m-d H:i'));
        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame($admin->id, $appointment->created_by);
        $this->assertDatabaseCount('appointment_status_events', 1);
        foreach (['day', 'week', 'month'] as $mode) {
            $this->get('/appointments?doctor_id='.$doctor->id.'&date=1406/01/01&view='.$mode)->assertOk()->assertSee($patient->last_name);
        }
        $this->get('/appointments/'.$appointment->public_id)->assertOk();
        $this->get('/schedules?doctor_id='.$doctor->id)->assertOk();
    }

    public function test_capacity_override_requires_privilege_and_reason_and_duplicate_patient_is_rejected(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking();
        $this->post('/appointments', $this->payload($doctor, $patient))->assertSessionHasNoErrors();
        $second = Patient::factory()->create();
        $this->post('/appointments', $this->payload($doctor, $second))->assertSessionHasErrors('starts_at');
        $this->post('/appointments', $this->payload($doctor, $second, ['override_reason' => 'Urgent care']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('appointments', ['patient_id' => $second->id, 'override_by' => $admin->id, 'override_reason' => 'Urgent care']);
        $this->post('/appointments', $this->payload($doctor, $patient, ['override_reason' => 'Duplicate']))->assertSessionHasErrors('patient_id');
        $reception = $this->account('general-receptionist');
        $this->actingAs($reception)->post('/appointments', $this->payload($doctor, Patient::factory()->create(), ['override_reason' => 'Not authorized']))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_reschedule_releases_capacity_and_rejects_stale_revision_and_closed_time(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking();
        $this->post('/appointments', $this->payload($doctor, $patient))->assertSessionHasNoErrors();
        $appointment = Appointment::sole();
        $url = '/appointments/'.$appointment->public_id;
        $this->put($url, ['revision' => 1, 'starts_at' => '2027-03-21T09:20', 'visit_type' => 'review'])->assertSessionHasNoErrors();
        $this->put($url, ['revision' => 1, 'starts_at' => '2027-03-21T09:40', 'visit_type' => 'review'])->assertSessionHasErrors('revision');
        $this->post('/appointments', $this->payload($doctor, Patient::factory()->create()))->assertSessionHasNoErrors();
        $this->post('/appointments', $this->payload($doctor, $patient, ['starts_at' => '2027-03-21T09:10']))->assertSessionHasErrors('starts_at');
        $this->post('/appointments', $this->payload($doctor, $patient, ['starts_at' => '2027-03-21T12:00']))->assertSessionHasErrors('starts_at');
        $this->assertSame(2, $appointment->fresh()->revision);
    }

    public function test_reception_numbers_status_history_and_invalid_transitions(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking();
        $this->post('/appointments', $this->payload($doctor, $patient))->assertSessionHasNoErrors();
        $appointment = Appointment::sole();
        $url = '/appointments/'.$appointment->public_id.'/status';
        $this->post($url, ['status' => 'completed', 'revision' => 1])->assertSessionHasErrors('status');
        $this->post($url, ['status' => 'waiting', 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('queue_entries', ['appointment_id' => $appointment->id, 'queue_number' => 1, 'local_date' => '2027-03-21']);
        $this->post($url, ['status' => 'waiting', 'revision' => 1])->assertSessionHasErrors('revision');
        $this->post($url, ['status' => 'in_progress', 'revision' => 2])->assertSessionHasNoErrors();
        $this->post($url, ['status' => 'completed', 'revision' => 3])->assertSessionHasNoErrors();
        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertDatabaseCount('appointment_status_events', 4);
    }

    public function test_cancellation_requires_reason_and_frees_capacity(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking();
        $this->post('/appointments', $this->payload($doctor, $patient))->assertSessionHasNoErrors();
        $appointment = Appointment::sole();
        $url = '/appointments/'.$appointment->public_id.'/status';
        $this->post($url, ['status' => 'cancelled', 'revision' => 1])->assertSessionHasErrors('reason');
        $this->post($url, ['status' => 'cancelled', 'revision' => 1, 'reason' => 'Patient request'])->assertSessionHasNoErrors();
        $this->post('/appointments', $this->payload($doctor, Patient::factory()->create()))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('appointment_status_events', ['to_status' => 'cancelled', 'reason' => 'Patient request']);
    }

    public function test_schedule_overlap_holiday_break_and_booked_day_protection(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking();
        $rule = ['doctor_id' => $doctor->id, 'weekday' => 0, 'local_start' => '10:00', 'local_end' => '13:00', 'effective_from' => '2027-03-01', 'slot_minutes' => 20, 'capacity' => 1];
        $this->post('/schedules', $rule)->assertSessionHasErrors('local_start');
        $this->post('/schedules', ['doctor_id' => $doctor->id, 'type' => 'exception', 'kind' => 'break', 'local_date' => '2027-03-21', 'local_start' => '09:20', 'local_end' => '09:40'])->assertSessionHasNoErrors();
        $this->post('/appointments', $this->payload($doctor, $patient, ['starts_at' => '2027-03-21T09:20']))->assertSessionHasErrors('starts_at');
        $this->post('/appointments', $this->payload($doctor, $patient))->assertSessionHasNoErrors();
        $this->post('/schedules', ['doctor_id' => $doctor->id, 'type' => 'exception', 'kind' => 'closed', 'local_date' => '2027-03-21'])->assertSessionHasErrors('local_date');
        $this->delete('/schedules/rule/'.DoctorScheduleRule::sole()->id)->assertSessionHasErrors('schedule');
        $this->post('/schedules', ['doctor_id' => $doctor->id, 'type' => 'exception', 'kind' => 'closed', 'local_date' => '2027-03-28'])->assertSessionHasNoErrors();
        $this->assertSame([], app(ScheduleAvailability::class)->slots($doctor, CarbonImmutable::parse('2027-03-28', 'Asia/Tehran')));
    }

    public function test_unassigned_doctors_guests_and_unprivileged_accounts_cannot_access_appointments(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking();
        $this->post('/appointments', $this->payload($doctor, $patient))->assertSessionHasNoErrors();
        $appointment = Appointment::sole();
        $otherDoctor = Doctor::factory()->create(['user_id' => $this->account('doctor')->id]);
        $this->actingAs($otherDoctor->user)->get('/appointments/'.$appointment->public_id)->assertNotFound();
        $this->post('/appointments', $this->payload($doctor, $patient))->assertNotFound();
        $this->post('/schedules', ['doctor_id' => $doctor->id])->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/appointments')->assertForbidden();
        $this->post('/appointments', [])->assertForbidden();
        auth()->logout();
        $this->get('/appointments')->assertRedirect('/login');
    }

    public function test_persian_leap_day_and_month_boundary_are_strict(): void
    {
        $this->assertSame('2025-03-20', PersianDate::parse('1403/12/30'));
        $this->assertNull(PersianDate::parse('1404/12/30'));
        $this->assertSame('2027-03-21', PersianDate::parse('1406/01/01'));
        $this->assertSame('1406/01/01 00:15', PersianDate::format(CarbonImmutable::parse('2027-03-20 20:45', 'UTC'), true));
    }

    public function test_reception_rejects_wrong_day_early_no_show_and_parallel_consultations(): void
    {
        [$admin, $doctor, $patient] = $this->setupBooking(2);
        $this->post('/appointments', $this->payload($doctor, $patient))->assertSessionHasNoErrors();
        $first = Appointment::sole();
        $this->post('/appointments', $this->payload($doctor, Patient::factory()->create()))->assertSessionHasNoErrors();
        $second = Appointment::orderByDesc('id')->first();
        $this->post('/appointments/'.$first->public_id.'/status', ['status' => 'no_show', 'revision' => 1])->assertSessionHasErrors('status');
        $this->travelTo(CarbonImmutable::parse('2027-03-20 07:00', 'Asia/Tehran'));
        $this->post('/appointments/'.$first->public_id.'/status', ['status' => 'waiting', 'revision' => 1])->assertSessionHasErrors('status');
        $this->travelTo(CarbonImmutable::parse('2027-03-21 08:00', 'Asia/Tehran'));
        foreach ([$first, $second] as $item) {
            $this->post('/appointments/'.$item->public_id.'/status', ['status' => 'waiting', 'revision' => 1])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('queue_entries', ['appointment_id' => $second->id, 'queue_number' => 2]);
        $this->post('/appointments/'.$first->public_id.'/status', ['status' => 'in_progress', 'revision' => 2])->assertSessionHasNoErrors();
        $this->post('/appointments/'.$second->public_id.'/status', ['status' => 'in_progress', 'revision' => 2])->assertSessionHasErrors('status');
        $this->get('/appointments?date=1406/01/01')->assertOk()->assertSee(__('appointments.queue'));
    }
}
