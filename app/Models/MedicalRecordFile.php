<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MedicalRecordFile extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id', 'public_id'];

    protected static function booted(): void
    {
        static::creating(fn (MedicalRecordFile $file) => $file->public_id = (string) Str::ulid());
        static::updating(fn (): never => abort(409, 'Medical record files are immutable.'));
        static::deleting(fn (): never => abort(409, 'Medical record files are immutable.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(MedicalEncounter::class, 'medical_encounter_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
