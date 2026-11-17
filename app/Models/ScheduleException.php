<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleException extends Model
{
    protected $fillable = ['doctor_id', 'local_date', 'kind', 'local_start', 'local_end', 'reason', 'created_by'];
}
