<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            $submission->public_id = (string) \Illuminate\Support\Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function version(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }
}
