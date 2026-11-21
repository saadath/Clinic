@php($definition = app(\App\Services\Forms\FieldTypeRegistry::class)->get($field['type']))
@php($model = isset($repeaterKey) ? "row['".$field['key']."']" : "values['".$field['key']."']")
@php($valuesPrefix = $valuesPrefix ?? 'values')
@php($inputName = isset($repeaterKey) ? "'".$valuesPrefix.'['.$repeaterKey."][' + rowId + '][".$field['key']."]'" : "'".$valuesPrefix.'['.$field['key']."]'")
@if($field['type'] === 'heading')<h3 class="font-bold">{{ $field['label'] }}</h3>
@elseif($field['type'] === 'description')<p class="whitespace-pre-wrap text-sm">{{ $field['label'] }} {{ $field['description'] }}</p>
@elseif($field['type'] === 'divider')<hr class="border-slate-200 dark:border-slate-700">
@elseif($definition['kind'] === 'file')
    <div class="grid gap-2 text-sm">
        @php($fileInputId = $valuesPrefix.'-'.$field['key'])
        <label for="{{ $fileInputId }}">{{ $field['label'] }} {{ $field['required'] ? '*' : '' }}</label>
        @if($editable)
            @if(in_array($field['type'], ['image', 'multiple_images', 'pdf'], true))
                @include('forms.file-picker', ['inputId' => $fileInputId, 'inputName' => $inputName, 'multiple' => app(\App\Services\Forms\FieldTypeRegistry::class)->acceptsMultipleFiles($field['type']), 'pdf' => $field['type'] === 'pdf'])
            @else
                <input id="{{ $fileInputId }}" class="input" :name="{{ $inputName }}" type="file" accept="{{ match($field['type']) { 'signature' => 'image/jpeg,image/png,image/webp', 'audio' => 'audio/*', 'video' => 'video/*', default => '.pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx,.xls,.xlsx' } }}" @required($field['required'])>
            @endif
        @elseif($submission)
            @foreach($submission->files->where('field_key', $field['key']) as $file)
                <a class="text-teal-700 underline dark:text-teal-300" href="{{ route('form-submission-files.show', $file) }}">{{ $file->original_name }}</a>
            @endforeach
        @endif
        @if($field['description'])<span class="text-xs text-slate-500">{{ $field['description'] }}</span>@endif
    </div>
@elseif($field['type'] === 'calculated')<div class="grid gap-2 text-sm"><span>{{ $field['label'] }} {{ $field['unit'] }}</span><output class="input" x-text="calculate('{{ $field['key'] }}') ?? '—'"></output></div>
@elseif($field['type'] === 'single_checkbox')
    <div class="grid gap-2 text-sm">
        @if($editable)<input type="hidden" :name="{{ $inputName }}" :value="[true, 1, '1'].includes({{ $model }}) ? '1' : '0'">@endif
        <label @class(['inline-flex items-center gap-2', 'cursor-pointer' => $editable])>
            <input type="checkbox" :checked="[true, 1, '1'].includes({{ $model }})" @change="{{ $model }} = $event.target.checked" @required($field['required']) @disabled(!$editable)>
            <span>{{ $field['label'] }} {{ $field['required'] ? '*' : '' }}</span>
        </label>
        @if($field['description'])<span class="text-xs text-slate-500">{{ $field['description'] }}</span>@endif
    </div>
@elseif(in_array($field['type'], ['radio', 'checkbox'], true))
    <fieldset class="grid min-w-0 gap-2 border-0 p-0 text-sm" @disabled(!$editable)>
        <legend class="mb-2">{{ $field['label'] }} {{ $field['required'] ? '*' : '' }} {{ $field['unit'] }}</legend>
        <div @class(['flex', ($field['options_layout'] ?? 'horizontal') === 'vertical' ? 'flex-col items-start gap-2' : 'flex-wrap gap-4'])>
            @foreach($field['options'] as $option)
                <label @class(['inline-flex items-center gap-2', 'cursor-pointer' => $editable])>
                    <input type="{{ $field['type'] }}" :name="{{ $inputName }}{{ $field['type'] === 'checkbox' ? " + '[]'" : '' }}" value="{{ $option }}" x-model="{{ $model }}" @required($field['type'] === 'radio' && $field['required'])>
                    <span>{{ $option }}</span>
                </label>
            @endforeach
        </div>
        @if($field['description'])<span class="text-xs text-slate-500">{{ $field['description'] }}</span>@endif
    </fieldset>
@else
    <label class="grid gap-2 text-sm"><span>{{ $field['label'] }} {{ $field['required'] ? '*' : '' }} {{ $field['unit'] }}</span>
        @if($definition['input'] === 'textarea')
            <textarea class="input" :name="{{ $inputName }}" x-model="{{ $model }}" rows="4" @disabled(!$editable)></textarea>
        @elseif($definition['kind'] === 'choice')
            <select class="input" :name="{{ $inputName }}{{ $field['type'] === 'multi_select' ? " + '[]'" : '' }}" x-model="{{ $model }}" @if(in_array($field['type'], ['checkbox','multi_select'])) multiple @endif @disabled(!$editable)>
                @unless(in_array($field['type'], ['checkbox','multi_select']))<option value="">—</option>@endunless
                @foreach($field['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
            </select>
        @elseif($field['type'] === 'slider')
            @php($sliderMin = (int) ceil($field['min'] ?? 0))
            @php($sliderMax = (int) floor($field['max'] ?? 100))
            @if(!$editable || $sliderMin > $sliderMax)
                <output class="text-sm font-semibold" dir="ltr" x-text="{{ $model }} == null || {{ $model }} === '' ? '—' : Math.round({{ $model }})"></output>
            @else
                <span class="grid gap-2" dir="ltr">
                    <input class="w-full accent-teal-600" :name="{{ $inputName }}" x-model.number="{{ $model }}" type="range" min="{{ $sliderMin }}" max="{{ $sliderMax }}" step="1" x-init="$nextTick(() => { {{ $model }} = $el.valueAsNumber })">
                    <span class="flex items-center justify-between text-xs text-slate-500"><span>{{ $sliderMin }}</span><output class="text-sm font-semibold text-teal-700 dark:text-teal-300" x-text="Math.round({{ $model }})"></output><span>{{ $sliderMax }}</span></span>
                </span>
            @endif
        @else
            <input class="input" :name="{{ $inputName }}" x-model="{{ $model }}" type="{{ $definition['input'] }}" @if($definition['kind'] === 'number') step="{{ $field['type'] === 'integer' ? '1' : 'any' }}" @if($field['min'] !== null) min="{{ $field['min'] }}" @endif @if($field['max'] !== null) max="{{ $field['max'] }}" @endif @endif @disabled(!$editable)>
        @endif
        @if($field['description'])<span class="text-xs text-slate-500">{{ $field['description'] }}</span>@endif
        @if($field['type'] === 'rich_text')<span class="text-xs text-slate-500">{{ __('forms.rich_notice') }}</span>@endif
    </label>
@endif
