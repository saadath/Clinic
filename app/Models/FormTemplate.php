<?php

namespace App\Models;

use Database\Factories\FormTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FormTemplate extends Model
{
    /** @use HasFactory<FormTemplateFactory> */
    use HasFactory;

    protected $fillable = ['name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (FormTemplate $template): void {
            $template->public_id = (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'owner_doctor_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(FormVersion::class)->ofMany('version_number', 'max');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'form_template_shares', 'form_template_id', 'recipient_doctor_id')->withPivot('granted_by')->withTimestamps();
    }
}
