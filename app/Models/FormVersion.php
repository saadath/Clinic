<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['schema_json' => 'array', 'published_at' => 'datetime', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (FormVersion $version): void {
            abort_if(
                $version->getRawOriginal('status') === 'published'
                    && $version->submissions()->exists(),
                409,
                'Versions with saved submissions are immutable.',
            );
        });
        static::deleting(function (FormVersion $version): void {
            abort_if($version->status === 'published', 409, 'Published versions are immutable.');
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_version_id');
    }
}
