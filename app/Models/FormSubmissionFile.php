<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FormSubmissionFile extends Model
{
    protected $guarded = ['id', 'public_id'];

    protected static function booted(): void
    {
        static::creating(fn (FormSubmissionFile $file) => $file->public_id = (string) Str::ulid());
        static::updating(fn (): never => abort(409, 'Form submission files are immutable.'));
        static::deleting(fn (): never => abort(409, 'Form submission files are immutable.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isImagePreview(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public function isPdfPreview(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }
}
