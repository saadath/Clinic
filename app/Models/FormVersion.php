<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            abort_if($version->getRawOriginal('status') === 'published', 409, 'Published versions are immutable.');
        });
        static::deleting(function (FormVersion $version): void {
            abort_if($version->status === 'published', 409, 'Published versions are immutable.');
        });
    }

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }
}
