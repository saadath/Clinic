<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Appointments\PlannerCalendar;
use App\Services\Appointments\ScheduleAvailability;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WeeklySchedulePlannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function planner(): array
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-21 07:00', 'Asia/Tehran'));
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super-admin')->sole());
        $this->actingAs($admin);
        $doctor = Doctor::factory()->create();

        return [$admin, $doctor, ['doctor_id' => $doctor->id, 'starts_on' => '1406/01/01', 'period' => 'months', 'months' => 1, 'reason' => 'برنامه حضور', 'days' => array_map(fn ($weekday) => ['weekday' => $weekday, 'shifts' => [['key' => 'morning', 'local_start' => '09:00', 'local_end' => '12:00', 'slot_minutes' => 20, 'capacity' => 1, 'break_start' => null, 'break_end' => null]]], range(0, 6))]];
    }

    private function change(Doctor $doctor, array $changes = []): array
    {
        return array_replace(['doctor_id' => $doctor->id, 'dates' => ['1406/01/01'], 'revision' => 1, 'kind' => 'closed', 'reason' => 'تغییر حضور'], $changes);
    }

    private function book(Doctor $doctor, string $start = '2027-03-21T09:00'): Appointment
    {
        $this->post('/appointments', ['doctor_id' => $doctor->id, 'patient_id' => Patient::factory()->create()->id, 'starts_at' => $start, 'visit_type' => 'consultation'])->assertSessionHasNoErrors();

        return Appointment::latest('id')->firstOrFail();
    }

    #[TestWith([1, '2027-04-20', 31])]
    #[TestWith([3, '2027-06-21', 93])]
    #[TestWith([12, '2028-03-19', 365])]
    public function test_replication_uses_persian_month_boundaries(int $months, string $end, int $days): void
    {
        [, , $data] = $this->planner();
        $data['months'] = $months;

        $this->postJson('/schedule-planner/preview', $data)->assertOk()->assertJsonPath('end', $end)->assertJsonPath('working_days', $days);
        $this->postJson('/schedule-planner', $data)->assertOk();

        $this->assertDatabaseCount('doctor_schedule_days', $days);
        $this->assertDatabaseCount('doctor_schedule_shifts', $days);
        $this->assertDatabaseCount('schedule_change_logs', $days);
    }

    public function test_independent_day_edit_preserves_base_and_other_weeks_and_rejects_stale_revision(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();

        $this->postJson('/schedule-planner/day', $this->change($doctor))->assertOk()->assertJsonPath('changed_days', 1);
        $this->postJson('/schedule-planner/day', $this->change($doctor))->assertUnprocessable()->assertJsonValidationErrors('schedule');

        $first = DB::table('doctor_schedule_days')->where('local_date', '2027-03-21')->sole();
        $next = DB::table('doctor_schedule_days')->where('local_date', '2027-03-28')->sole();
        $this->assertSame('[]', $first->final_json);
        $this->assertSame($first->base_json, $next->final_json);
        $this->assertSame(1, $next->revision);
    }

    public function test_closing_booked_day_rolls_back_without_confirmation_and_preserves_booking_after_confirmation(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();
        $appointment = $this->book($doctor);
        $before = $appointment->getAttributes();

        $this->postJson('/schedule-planner/day', $this->change($doctor))->assertUnprocessable()->assertJsonValidationErrors('conflicts');
        $this->assertDatabaseCount('appointment_schedule_conflicts', 0);
        $this->assertDatabaseHas('doctor_schedule_days', ['local_date' => '2027-03-21', 'revision' => 1]);
        $this->postJson('/schedule-planner/day', $this->change($doctor, ['confirm_conflicts' => true]))->assertOk()->assertJsonPath('conflicts', 1);

        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('appointment_schedule_conflicts', ['appointment_id' => $appointment->id, 'status' => 'review_required']);
        $this->get('/schedule-conflicts')->assertOk()->assertSee($appointment->patient->last_name);
    }

    public function test_reschedule_requires_patient_response_and_rechecks_capacity_before_final_confirmation(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();
        $appointment = $this->book($doctor);
        $this->postJson('/schedule-planner/day', $this->change($doctor, ['confirm_conflicts' => true]))->assertOk();
        $id = DB::table('appointment_schedule_conflicts')->value('id');
        $url = '/schedule-conflicts/'.$id;
        $this->post($url, ['revision' => 1, 'status' => 'time_proposed', 'proposed_start' => '1406/01/02 09:00'])->assertSessionHasNoErrors();
        $proposal = DB::table('appointment_reschedule_requests')->sole();
        $this->post($url, ['revision' => 2, 'status' => 'patient_confirmed', 'proposal_id' => $proposal->id])->assertSessionHasErrors('conflict');
        $this->book($doctor, '2027-03-22T09:00');

        $this->post($url, ['revision' => 2, 'status' => 'patient_confirmed', 'proposal_id' => $proposal->id, 'patient_response' => 'تأیید بیمار'])->assertSessionHasErrors('conflict');

        $this->assertSame('2027-03-21 05:30', $appointment->fresh()->starts_at->format('Y-m-d H:i'));
        $this->assertDatabaseHas('appointment_reschedule_requests', ['id' => $proposal->id, 'status' => 'proposed']);
        $this->post($url, ['revision' => 2, 'status' => 'time_proposed', 'proposed_start' => '1406/01/02 09:20'])->assertSessionHasNoErrors();
        $proposal = DB::table('appointment_reschedule_requests')->latest('id')->first();
        $this->post($url, ['revision' => 3, 'status' => 'patient_confirmed', 'proposal_id' => $proposal->id, 'patient_response' => 'تأیید بیمار'])->assertSessionHasNoErrors();
        $this->assertSame('2027-03-22 05:50', $appointment->fresh()->starts_at->format('Y-m-d H:i'));
        $this->assertDatabaseHas('appointment_schedule_conflicts', ['id' => $id, 'status' => 'patient_confirmed']);
        $this->assertDatabaseCount('appointment_status_events', 3);
    }

    public function test_guests_and_unprivileged_users_cannot_manage_planner(): void
    {
        $this->get('/schedule-planner')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/schedule-planner')->assertForbidden();
    }

    public function test_invalid_24_hour_times_and_overlapping_shifts_are_rejected_without_writes(): void
    {
        [, , $data] = $this->planner();
        $data['days'][0]['shifts'][0]['local_start'] = '25:00';
        $this->postJson('/schedule-planner', $data)->assertUnprocessable()->assertJsonValidationErrors('days.0.shifts.0.local_start');
        $data['days'][0]['shifts'][0]['local_start'] = '09:00';
        $data['days'][0]['shifts'][] = array_replace($data['days'][0]['shifts'][0], ['key' => 'overlap']);
        $this->postJson('/schedule-planner', $data)->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertDatabaseCount('doctor_schedule_templates', 0);
    }

    public function test_planner_renders_without_branch_and_calendar_contains_every_day(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->get('/schedule-planner')->assertOk()->assertDontSee('شعبه')->assertDontSee('branch_id');
        $this->postJson('/schedule-planner', $data)->assertOk();

        $this->getJson('/schedule-planner/calendar?doctor_id='.$doctor->id.'&date=1406/01/01')
            ->assertOk()->assertJsonCount(31, 'days')->assertJsonPath('padding', 1)
            ->assertJsonPath('days.0.persian', '1406/01/01')->assertJsonPath('days.30.persian', '1406/01/31');
    }

    public function test_clinic_closure_preserves_bookings_and_cannot_be_overridden_by_daily_shifts(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();
        $appointment = $this->book($doctor);
        $closure = ['dates' => ['1406/01/01'], 'reason' => 'تعطیلی کلینیک', 'kind' => 'clinic'];
        $this->postJson('/schedule-planner/closures', $closure)->assertUnprocessable()->assertJsonValidationErrors('conflicts');
        $this->assertDatabaseCount('schedule_closures', 0);
        $this->postJson('/schedule-planner/closures', $closure + ['confirm_conflicts' => true])->assertOk()->assertJsonPath('conflicts', 1);
        $this->assertSame('scheduled', $appointment->fresh()->status);

        $this->postJson('/schedule-planner/day', $this->change($doctor, ['kind' => 'replace', 'shifts' => $data['days'][0]['shifts'], 'confirm_conflicts' => true]))->assertOk();

        $this->assertSame([], app(ScheduleAvailability::class)->slots($doctor, CarbonImmutable::parse('2027-03-21', 'Asia/Tehran')));
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_visit_duration_change_detects_all_affected_bookings_without_changing_them(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();
        $first = $this->book($doctor);
        $second = $this->book($doctor, '2027-03-21T09:20');
        $shifts = $data['days'][0]['shifts'];
        $shifts[0]['slot_minutes'] = 30;

        $this->postJson('/schedule-planner/day', $this->change($doctor, ['kind' => 'replace', 'shifts' => $shifts, 'confirm_conflicts' => true]))->assertOk()->assertJsonPath('conflicts', 2);

        $this->assertSame(20, (int) $first->fresh()->starts_at->diffInMinutes($first->fresh()->ends_at));
        $this->assertSame('scheduled', $second->fresh()->status);
        $this->assertDatabaseCount('appointment_schedule_conflicts', 2);
    }

    public function test_persian_month_week_titles_and_cross_month_multi_date_changes(): void
    {
        [, $doctor, $data] = $this->planner();
        $data['starts_on'] = '1406/07/01';
        $data['months'] = 3;
        $this->postJson('/schedule-planner', $data)->assertOk();
        $calendar = app(PlannerCalendar::class);
        $this->getJson('/schedule-planner/calendar?doctor_id='.$doctor->id.'&date=1406/07/01')->assertOk()->assertJsonPath('weeks.1.label', 'هفته دوم مهر 1406 — 1406/07/03 تا 1406/07/09');
        $this->getJson('/schedule-planner/calendar?doctor_id='.$doctor->id.'&date=1406/08/01')->assertOk()->assertJsonPath('weeks.2.label', 'هفته سوم آبان 1406 — 1406/08/15 تا 1406/08/21');
        $dates = ['1406/07/30', '1406/08/01'];
        $revisions = [];
        foreach ($dates as $date) {
            $revisions[$calendar->date($date)->toDateString()] = 1;
        }

        $this->postJson('/schedule-planner/day', $this->change($doctor, ['dates' => $dates, 'revisions' => $revisions]))->assertOk()->assertJsonPath('changed_days', 2);

        foreach (array_keys($revisions) as $date) {
            $this->assertDatabaseHas('doctor_schedule_days', ['local_date' => $date, 'revision' => 2]);
            $this->assertSame([], json_decode(DB::table('doctor_schedule_days')->where('local_date', $date)->value('final_json'), true));
        }
    }

    public function test_phone_failures_and_unconfigured_channels_are_logged_honestly(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();
        $this->book($doctor);
        $this->postJson('/schedule-planner/day', $this->change($doctor, ['confirm_conflicts' => true]))->assertOk();
        $id = DB::table('appointment_schedule_conflicts')->value('id');
        $this->get('/schedule-conflicts/'.$id)->assertOk();

        $this->post('/schedule-conflicts/'.$id.'/notify', ['channel' => 'phone', 'delivery' => 'failed', 'result' => 'تماس ناموفق'])->assertSessionHasNoErrors();
        $this->post('/schedule-conflicts/'.$id.'/notify', ['channel' => 'sms', 'delivery' => 'contacted', 'result' => 'درخواست پیامک'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_logs', ['conflict_id' => $id, 'channel' => 'phone', 'status' => 'failed']);
        $this->assertDatabaseHas('notification_logs', ['conflict_id' => $id, 'channel' => 'sms', 'status' => 'not_configured']);
    }

    #[TestWith(['week', 7])]
    #[TestWith(['two_weeks', 14])]
    public function test_week_cards_accept_zero_months(string $period, int $days): void
    {
        [, , $data] = $this->planner();
        $data['period'] = $period;
        $data['months'] = 0;

        $this->postJson('/schedule-planner/preview', $data)->assertOk()->assertJsonPath('working_days', $days);
    }

    public function test_unprivileged_reception_cannot_confirm_booked_day_closure(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();
        $this->book($doctor);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('name', 'general-receptionist')->sole());

        $this->actingAs($staff)->postJson('/schedule-planner/day', $this->change($doctor, ['confirm_conflicts' => true]))->assertUnprocessable()->assertJsonValidationErrors('conflicts');

        $this->assertDatabaseCount('appointment_schedule_conflicts', 0);
        $this->assertDatabaseHas('doctor_schedule_days', ['local_date' => '2027-03-21', 'revision' => 1]);
        $this->postJson('/schedule-planner/closures', ['dates' => ['1406/01/01'], 'kind' => 'clinic', 'reason' => 'تعطیلی'])->assertForbidden();
    }

    public function test_day_time_change_detects_multiple_bookings_and_keeps_history(): void
    {
        [, $doctor, $data] = $this->planner();
        $this->postJson('/schedule-planner', $data)->assertOk();
        $this->book($doctor);
        $this->book($doctor, '2027-03-21T09:20');
        $shifts = $data['days'][0]['shifts'];
        $shifts[0]['local_start'] = '10:00';

        $this->postJson('/schedule-planner/day', $this->change($doctor, ['kind' => 'replace', 'shifts' => $shifts, 'confirm_conflicts' => true]))->assertOk()->assertJsonPath('conflicts', 2);

        $this->assertDatabaseCount('appointment_status_events', 2);
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseHas('schedule_change_logs', ['doctor_id' => $doctor->id, 'local_date' => '2027-03-21', 'action' => 'replace']);
        $this->get('/schedule-conflicts?date=1406/01/01')->assertOk()->assertSee('نیازمند تغییر برنامه');
    }
}
