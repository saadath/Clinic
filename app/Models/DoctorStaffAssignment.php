<?php

namespace App\Models;

use Database\Factories\DoctorStaffAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorStaffAssignment extends Model
{
    /** @use HasFactory<DoctorStaffAssignmentFactory> */
    use HasFactory;

    protected $fillable = ['doctor_id', 'staff_user_id', 'assignment_type', 'starts_at', 'ends_at', 'assigned_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function scopeCurrentFor(Builder $query, User $user): Builder
    {
        $types = [];
        if ($user->hasRole('receptionist') || $user->hasRole('general-receptionist')) {
            $types[] = 'receptionist';
        }
        if ($user->hasRole('doctor-assistant')) {
            $types[] = 'assistant';
        }

        return $query->where('staff_user_id', $user->id)->whereIn('assignment_type', $types)
            ->where('starts_at', '<=', now())
            ->where(fn ($period) => $period->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
