<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorScheduleRule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AppointmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_independent_mysql_processes_cannot_take_the_last_slot(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This acceptance test requires MySQL row locks.');
        }
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::where('name', 'super-admin')->sole());
        $doctor = Doctor::factory()->create();
        $patients = Patient::factory()->count(2)->create();
        $date = CarbonImmutable::now('Asia/Tehran')->addDays(2)->startOfDay();
        DoctorScheduleRule::create(['doctor_id' => $doctor->id, 'weekday' => $date->dayOfWeek, 'local_start' => '09:00', 'local_end' => '10:00', 'effective_from' => $date->toDateString(), 'slot_minutes' => 20, 'capacity' => 1, 'created_by' => $actor->id]);
        $code = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
        config(['database.default' => 'mysql', 'database.connections.mysql' => $input['connection']]);
        Illuminate\Support\Facades\DB::purge();
        echo "ready\n";
        flush();
        try {
            $app->make(App\Services\Appointments\ManageAppointment::class)->save(App\Models\User::findOrFail($input['actor']), $input['booking']);
            echo 'booked';
        } catch (Illuminate\Validation\ValidationException $error) {
            echo array_key_exists('starts_at', $error->errors()) ? 'full' : 'unexpected-validation';
        }
        PHP;
        $processes = [];
        DB::beginTransaction();
        Doctor::whereKey($doctor->id)->lockForUpdate()->firstOrFail();
        try {
            foreach ($patients as $patient) {
                $process = new Process([PHP_BINARY, '-r', $code, json_encode(['connection' => config('database.connections.mysql'), 'actor' => $actor->id, 'booking' => ['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'starts_at' => $date->format('Y-m-d').'T09:00', 'visit_type' => 'consultation']], JSON_THROW_ON_ERROR)], base_path());
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
            }
        } finally {
            DB::commit();
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = trim(str_replace("ready\n", '', $process->getOutput()));
        }
        sort($results);
        $this->assertSame(['booked', 'full'], $results);
        $this->assertSame(1, Appointment::count());
        $this->assertDatabaseCount('appointment_status_events', 1);
    }
}
