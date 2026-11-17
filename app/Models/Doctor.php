<?php

namespace App\Models;

use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'medical_council_number', 'specialty', 'subspecialty', 'bio', 'visit_duration_minutes', 'visit_price_rial', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'visit_duration_minutes' => 'integer', 'visit_price_rial' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DoctorStaffAssignment::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('doctors.manage') || $user->hasPermission('doctors.view-all')) {
            return $query;
        }

        return $query->where('active', true)->whereHas('user', fn ($account) => $account->where('active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'doctor')))
            ->where(function ($visible) use ($user): void {
                $visible->where('user_id', $user->id)->orWhereHas('assignments', fn ($assignments) => $assignments->currentFor($user));
            });
    }
}
