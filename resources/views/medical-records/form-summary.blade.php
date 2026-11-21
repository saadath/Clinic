<section class="grid min-w-0 gap-3 pb-5 last:pb-0">
    <dl class="grid gap-3 text-sm">
        @foreach($form['fields'] as $field)
            @if($field['text'] !== null)
                <div class="min-w-0 wrap-anywhere">
                    <dt class="inline font-medium">{{ $field['label'] }}:</dt>
                    <dd class="inline whitespace-pre-wrap text-slate-700 dark:text-slate-300" dir="auto">{{ $field['text'] }}</dd>
                </div>
            @else
                <div class="grid min-w-0 gap-2">
                    <dt class="font-medium wrap-anywhere">{{ $field['label'] }}:</dt>
                    <dd class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,8rem),1fr))] gap-3">
                        @foreach($field['files'] as $file)
                            @php($url = route('form-submission-files.show', ['file' => $file, 'inline' => $file->isImagePreview() || $file->isPdfPreview() ? 1 : null]))
                            <figure class="min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                                <div class="relative grid aspect-[4/3] place-items-center bg-slate-100 dark:bg-slate-900">
                                    @if($file->isImagePreview())
                                        <img src="{{ $url }}" alt="{{ $file->original_name }}" loading="lazy" decoding="async" class="absolute inset-0 size-full object-contain">
                                    @else
                                        <span aria-hidden="true" class="grid justify-items-center gap-1 text-xs font-semibold text-slate-500 dark:text-slate-400"><x-icon name="file" class="size-7"/>{{ $file->isPdfPreview() ? 'PDF' : '' }}</span>
                                        @if($file->isPdfPreview())
                                            <template x-data x-if="navigator.pdfViewerEnabled">
                                                <iframe src="{{ $url }}#page=1&amp;toolbar=0&amp;navpanes=0&amp;scrollbar=0&amp;view=Fit" title="{{ $file->original_name }}" tabindex="-1" loading="lazy" class="pointer-events-none absolute inset-0 size-full border-0"></iframe>
                                            </template>
                                        @endif
                                    @endif
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('forms.open_file') }}: {{ $file->original_name }}" title="{{ __('forms.open_file') }}" class="absolute end-1.5 top-1.5 grid size-9 place-items-center rounded-lg bg-slate-950/80 text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-500">
                                        <x-icon name="open" class="size-4"/>
                                    </a>
                                </div>
                                <figcaption dir="auto" title="{{ $file->original_name }}" class="truncate px-2 py-1.5 text-xs">{{ $file->original_name }}</figcaption>
                            </figure>
                        @endforeach
                    </dd>
                </div>
            @endif
        @endforeach
    </dl>
</section>
