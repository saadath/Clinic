<x-staff-page :title="$version->template->name.' — '.__('forms.version').' '.$version->version_number">
    <p class="mb-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ __('forms.sample_notice') }}</p>
    @if($submission)<p class="mb-5 text-sm">{{ __('forms.sample_saved') }} {{ $version->version_number }}</p>@endif
    <form data-form-runtime x-data="formRuntime" data-schema="{{ json_encode($schema, JSON_THROW_ON_ERROR) }}" data-values="{{ json_encode(old('values', $values), JSON_THROW_ON_ERROR) }}" method="POST" action="{{ route('form-submissions.store', $version) }}" class="grid gap-5">
        @csrf
        @foreach($schema['sections'] as $section)
            <section class="panel"><h2 class="mb-5 text-lg font-bold">{{ $section['title'] }}</h2>
                <div @class(['grid gap-5', 'md:grid-cols-2' => $section['columns'] === 2, 'md:grid-cols-3' => $section['columns'] === 3])>
                    @foreach($section['fields'] as $field)
                        @if($field['active'])
                            @php($editable = !$submission && app(\App\Services\Forms\FormAccess::class)->fieldEditable(auth()->user(), $version->template, $field))
                            <div @class(['min-w-0', 'md:col-span-2' => min($field['width'], $section['columns']) === 2, 'md:col-span-3' => min($field['width'], $section['columns']) === 3]) x-show="visible('{{ $field['key'] }}')">
                                @if($field['type'] === 'repeater')
                                    <h3 class="mb-3 text-sm font-semibold">{{ $field['label'] }}</h3>
                                    <template x-for="(row, rowId) in rows['{{ $field['key'] }}']" :key="rowId">
                                        <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                            @foreach($field['children'] as $child)
                                                @if($child['active'])
                                                    @php($childEditable = $editable && app(\App\Services\Forms\FormAccess::class)->fieldEditable(auth()->user(), $version->template, $child))
                                                    @include('forms.field', ['field' => $child, 'editable' => $childEditable, 'repeaterKey' => $field['key']])
                                                @endif
                                            @endforeach
                                            @if($editable)<div class="flex gap-4 text-sm"><button type="button" @click="moveRow('{{ $field['key'] }}', rowId, -1)">{{ __('forms.up') }}</button><button type="button" @click="moveRow('{{ $field['key'] }}', rowId, 1)">{{ __('forms.down') }}</button><button type="button" class="text-red-600" @click="delete rows['{{ $field['key'] }}'][rowId]">{{ __('forms.remove') }}</button></div>@endif
                                        </div>
                                    </template>
                                    @if($editable)<button type="button" class="text-sm text-teal-700 dark:text-teal-300" @click="addRow('{{ $field['key'] }}')">{{ __('forms.add_row') }}</button>@endif
                                @else
                                    @include('forms.field', ['field' => $field, 'editable' => $editable])
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
        @if(!$submission && $version->status === 'published' && $version->template->active)@can('forms.test')<button class="button justify-self-start">{{ __('forms.sample_save') }}</button>@endcan @endif
    </form>
</x-staff-page>
