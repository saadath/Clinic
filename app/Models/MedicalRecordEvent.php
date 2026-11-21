<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata_json' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => abort(409, 'Medical record events are immutable.'));
        static::deleting(fn (): never => abort(409, 'Medical record events are immutable.'));
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(MedicalEncounter::class, 'medical_encounter_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
