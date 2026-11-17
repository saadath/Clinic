<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorScheduleRule extends Model
{
    protected $fillable = ['doctor_id', 'weekday', 'local_start', 'local_end', 'effective_from', 'effective_to', 'slot_minutes', 'capacity', 'created_by'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'slot_minutes' => 'integer', 'capacity' => 'integer'];
    }
}
