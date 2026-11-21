<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordAmendment extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn (): never => abort(409, 'Medical record amendments are immutable.'));
        static::deleting(fn (): never => abort(409, 'Medical record amendments are immutable.'));
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(MedicalEncounter::class, 'medical_encounter_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
