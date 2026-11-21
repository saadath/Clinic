<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MedicalEncounter extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'public_id', 'status', 'confirmed_at', 'confirmed_by', 'deleted_at'];

    protected static function booted(): void
    {
        static::creating(function (MedicalEncounter $encounter): void {
            $encounter->public_id = (string) Str::ulid();
        });
        static::forceDeleting(function (): never {
            abort(409, 'Medical encounters cannot be permanently deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MedicalRecordEvent::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(MedicalRecordAmendment::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(MedicalRecordFile::class);
    }
}
