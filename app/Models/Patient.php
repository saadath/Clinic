<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected $fillable = ['first_name', 'last_name', 'national_id', 'mobile', 'birth_date', 'sex', 'email', 'occupation', 'marital_status', 'emergency_name', 'emergency_mobile', 'insurance_name', 'insurance_number', 'referral_source', 'address', 'notes'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (Patient $patient): void {
            $patient->public_id = (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'patient_doctor')->withPivot('granted_by')->withTimestamps();
    }

    public function medicalEncounters(): HasMany
    {
        return $this->hasMany(MedicalEncounter::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('patients.view-all')) {
            return $query;
        }

        return $query->whereHas('doctors', fn ($doctors) => $doctors->whereIn('doctors.id', Doctor::visibleTo($user)->select('doctors.id')));
    }
}
