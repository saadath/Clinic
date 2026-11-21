<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['values_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (FormSubmission $submission): void {
            $submission->public_id = (string) Str::ulid();
        });
        static::deleting(fn (): never => abort(409, 'Form submissions cannot be deleted.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(MedicalEncounter::class, 'medical_encounter_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FormSubmissionFile::class, 'form_submission_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
