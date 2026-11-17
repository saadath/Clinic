<?php

namespace App\Services\Forms;

use App\Models\Doctor;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FormAccess
{
    public function query(User $user): Builder
    {
        $query = FormTemplate::query();
        if ($user->can('forms.manage-all')) {
            return $query;
        }

        return $query->whereIn('owner_doctor_id', Doctor::visibleTo($user)->select('id'));
    }

    public function authorize(User $user, FormTemplate $template, bool $manage = false): void
    {
        abort_unless($user->can($manage ? 'forms.manage' : 'forms.view'), 403);
        abort_unless($this->query($user)->whereKey($template->id)->exists(), 404);
        if ($manage && ! $user->can('forms.manage-all')) {
            abort_unless($template->doctor->user_id === $user->id && $user->hasRole('doctor'), 403);
        }
    }

    public function owner(User $user, int $doctorId): Doctor
    {
        abort_unless($user->can('forms.manage'), 403);
        $doctor = Doctor::where('active', true)->whereHas('user', fn ($query) => $query->where('active', true))->findOrFail($doctorId);
        abort_unless($user->can('forms.manage-all') || ($doctor->user_id === $user->id && $user->hasRole('doctor')), 403);

        return $doctor;
    }

    public function fieldVisible(User $user, FormTemplate $template, array $field): bool
    {
        if ($user->can('forms.manage-all') || $template->doctor->user_id === $user->id) {
            return true;
        }
        if ($field['confidentiality'] === 'confidential') {
            return false;
        }

        return $user->hasRole('doctor-assistant') || $field['receptionist_visible'];
    }

    public function fieldEditable(User $user, FormTemplate $template, array $field): bool
    {
        if (! $this->fieldVisible($user, $template, $field)) {
            return false;
        }

        return $user->can('forms.manage-all') || $template->doctor->user_id === $user->id || ($user->hasRole('doctor-assistant') && $field['assistant_editable']);
    }

    public function schemaFor(User $user, FormTemplate $template, array $schema): array
    {
        foreach ($schema['sections'] as &$section) {
            $section['fields'] = array_values(array_filter($section['fields'], fn ($field) => $this->fieldVisible($user, $template, $field)));
            foreach ($section['fields'] as &$field) {
                if ($field['type'] === 'repeater') {
                    $field['children'] = array_values(array_filter($field['children'], fn ($child) => $this->fieldVisible($user, $template, $child)));
                }
            }
        }

        return $schema;
    }
}
