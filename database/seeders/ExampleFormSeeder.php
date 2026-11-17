<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\FormTemplate;
use App\Services\Forms\ManageForm;
use Illuminate\Database\Seeder;

class ExampleFormSeeder extends Seeder
{
    public function run(ManageForm $manager): void
    {
        foreach (Doctor::where('active', true)->with('user')->get() as $doctor) {
            if (! $doctor->user->can('forms.manage')) {
                continue;
            }
            foreach ($this->schemas() as $name => $schema) {
                if (FormTemplate::where('owner_doctor_id', $doctor->id)->where('name', $name)->exists()) {
                    continue;
                }
                $template = $manager->create($doctor->user, $doctor->id, $name, $schema);
                $manager->publish($doctor->user, $template->versions()->sole(), 1);
            }
        }
    }

    public function schemas(): array
    {
        return [
            'Internal Medicine — Example' => ['sections' => [['key' => 'internal', 'title' => 'Internal Medicine / داخلی', 'columns' => 2, 'fields' => [
                ['key' => 'hpi', 'type' => 'long_text', 'label' => 'HPI / شرح حال', 'assistant_editable' => true],
                ['key' => 'allergy', 'type' => 'select', 'label' => 'Drug allergy / حساسیت دارویی', 'options' => ['Yes', 'No'], 'assistant_editable' => true],
                ['key' => 'allergy_details', 'type' => 'long_text', 'label' => 'Allergy details / توضیحات حساسیت', 'condition' => ['op' => 'equals', 'field' => 'allergy', 'value' => 'Yes'], 'assistant_editable' => true],
                ['key' => 'examination', 'type' => 'long_text', 'label' => 'Examination / معاینه'],
                ['key' => 'weight', 'type' => 'medical_unit', 'label' => 'Weight', 'unit' => 'kg', 'required' => true, 'min' => 1, 'max' => 500, 'assistant_editable' => true],
                ['key' => 'height_m', 'type' => 'medical_unit', 'label' => 'Height', 'unit' => 'm', 'required' => true, 'min' => 0.2, 'max' => 3, 'assistant_editable' => true],
                ['key' => 'bmi', 'type' => 'calculated', 'label' => 'BMI (kg/m²)', 'calculation' => ['op' => '/', 'left' => ['field' => 'weight'], 'right' => ['op' => '*', 'left' => ['field' => 'height_m'], 'right' => ['field' => 'height_m']]]],
                ['key' => 'medications', 'type' => 'repeater', 'label' => 'Medications / داروها', 'assistant_editable' => true, 'children' => [['key' => 'drug_name', 'type' => 'short_text', 'label' => 'Drug name', 'assistant_editable' => true], ['key' => 'dose', 'type' => 'short_text', 'label' => 'Dose', 'assistant_editable' => true]]],
            ]]]],
            'Ophthalmology — Example' => ['sections' => [['key' => 'eye', 'title' => 'Ophthalmology / چشم', 'columns' => 2, 'fields' => [
                ['key' => 'va_right', 'type' => 'short_text', 'label' => 'Visual Acuity OD'],
                ['key' => 'va_left', 'type' => 'short_text', 'label' => 'Visual Acuity OS'],
                ['key' => 'iop_right', 'type' => 'medical_unit', 'label' => 'IOP OD', 'unit' => 'mmHg', 'min' => 0, 'max' => 100],
                ['key' => 'iop_left', 'type' => 'medical_unit', 'label' => 'IOP OS', 'unit' => 'mmHg', 'min' => 0, 'max' => 100],
                ['key' => 'slit_lamp', 'type' => 'long_text', 'label' => 'Slit Lamp Examination'],
                ['key' => 'fundoscopy', 'type' => 'long_text', 'label' => 'Fundoscopy'],
            ]]]],
        ];
    }
}
