@php($definition = app(\App\Services\Forms\FieldTypeRegistry::class)->get($field['type']))
@php($model = isset($repeaterKey) ? "row['".$field['key']."']" : "values['".$field['key']."']")
@php($inputName = isset($repeaterKey) ? "'values[".$repeaterKey."][' + rowId + '][".$field['key']."]'" : "'values[".$field['key']."]'")
@if($field['type'] === 'heading')<h3 class="font-bold">{{ $field['label'] }}</h3>
@elseif($field['type'] === 'description')<p class="whitespace-pre-wrap text-sm">{{ $field['label'] }} {{ $field['description'] }}</p>
@elseif($field['type'] === 'divider')<hr class="border-slate-200 dark:border-slate-700">
@elseif($definition['kind'] === 'file')<div class="grid gap-2 text-sm"><span>{{ $field['label'] }}</span><p class="text-xs text-slate-500">{{ __('forms.files_pending') }}</p></div>
@elseif($field['type'] === 'calculated')<div class="grid gap-2 text-sm"><span>{{ $field['label'] }} {{ $field['unit'] }}</span><output class="input" x-text="calculate('{{ $field['key'] }}') ?? '—'"></output></div>
@else
    <label class="grid gap-2 text-sm"><span>{{ $field['label'] }} {{ $field['required'] ? '*' : '' }} {{ $field['unit'] }}</span>
        @if($definition['input'] === 'textarea')
            <textarea class="input" :name="{{ $inputName }}" x-model="{{ $model }}" rows="4" @disabled(!$editable)></textarea>
        @elseif(in_array($field['type'], ['radio', 'checkbox'], true))
            <span class="flex flex-wrap gap-4">
                @foreach($field['options'] as $option)
                    <span class="flex items-center gap-2"><input type="{{ $field['type'] }}" :name="{{ $inputName }}{{ $field['type'] === 'checkbox' ? " + '[]'" : '' }}" value="{{ $option }}" x-model="{{ $model }}" @disabled(!$editable)><span>{{ $option }}</span></span>
                @endforeach
            </span>
        @elseif($definition['kind'] === 'choice')
            <select class="input" :name="{{ $inputName }}{{ $field['type'] === 'multi_select' ? " + '[]'" : '' }}" x-model="{{ $model }}" @if(in_array($field['type'], ['checkbox','multi_select'])) multiple @endif @disabled(!$editable)>
                @unless(in_array($field['type'], ['checkbox','multi_select']))<option value="">—</option>@endunless
                @foreach($field['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
            </select>
        @else
            <input class="input" :name="{{ $inputName }}" x-model="{{ $model }}" type="{{ $definition['input'] }}" @if($definition['kind'] === 'number') step="{{ $field['type'] === 'integer' ? '1' : 'any' }}" @if($field['min'] !== null) min="{{ $field['min'] }}" @endif @if($field['max'] !== null) max="{{ $field['max'] }}" @endif @endif @disabled(!$editable)>
        @endif
        @if($field['description'])<span class="text-xs text-slate-500">{{ $field['description'] }}</span>@endif
        @if($field['type'] === 'rich_text')<span class="text-xs text-slate-500">{{ __('forms.rich_notice') }}</span>@endif
    </label>
@endif
