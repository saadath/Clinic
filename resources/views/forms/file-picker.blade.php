<div x-data="multipleImagePicker" class="grid gap-3">
    @php($hint = $pdf ? __('forms.pdf_limit') : ($multiple ? __('forms.image_limit') : __('forms.single_image_limit')))
    <input id="{{ $inputId }}" x-ref="input" class="input" :name="{{ $inputName }}{{ $multiple ? " + '[]'" : '' }}" type="file" accept="{{ $pdf ? 'application/pdf,.pdf' : 'image/jpeg,image/png,image/webp' }}" @if($multiple) multiple @endif @required($field['required']) @change="previewFiles" data-limit-message="{{ $hint }}" data-invalid-message="{{ __('forms.file_invalid') }}" data-preview-kind="{{ $pdf ? 'pdf' : 'image' }}">
    <p class="text-xs text-slate-500">{{ $hint }}</p>
    <div x-show="previews.length" x-cloak class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <template x-for="(preview, index) in previews" :key="preview.url">
            <figure class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                @if($pdf)
                    <div class="relative grid aspect-square place-items-center bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-200">
                        <span class="grid justify-items-center gap-1 font-bold"><x-icon name="file" class="size-8"/>PDF</span>
                        <template x-if="preview.ready && canPreviewPdf"><iframe :src="preview.url + '#page=1&toolbar=0&navpanes=0&scrollbar=0&view=Fit'" :title="preview.name" tabindex="-1" loading="lazy" class="pointer-events-none absolute inset-0 size-full border-0"></iframe></template>
                    </div>
                @else
                    <img :src="preview.url" :alt="preview.name" class="aspect-square w-full object-cover">
                @endif
                <div class="absolute inset-x-0 top-0 flex justify-end gap-1 p-1.5">
                    <button type="button" class="rounded-lg bg-slate-950/80 p-1.5 text-white hover:bg-teal-700 disabled:opacity-50" title="{{ __('forms.open_file') }}" :aria-label="@js(__('forms.open_file')).concat(': ', preview.name)" :disabled="!preview.ready" @click="openPreview(preview)"><x-icon name="open" class="size-4"/></button>
                    <button type="button" class="rounded-lg bg-slate-950/80 p-1.5 text-white hover:bg-red-700" title="{{ __('forms.remove') }}" :aria-label="@js(__('forms.remove')).concat(': ', preview.name)" @click="removePreview(index)"><x-icon name="trash" class="size-4"/></button>
                </div>
                <figcaption class="truncate px-2 py-1.5 text-xs" dir="auto" x-text="preview.name"></figcaption>
            </figure>
        </template>
    </div>
    @unless($pdf)
    <dialog x-ref="dialog" class="m-auto max-h-[90vh] w-fit max-w-[90vw] rounded-2xl bg-slate-900 p-3 text-white backdrop:bg-black/70" aria-label="{{ __('forms.open_file') }}" @close="opened = null" @click.self="$refs.dialog.close()">
        <div class="grid gap-3">
            <button type="button" class="justify-self-end rounded-lg bg-white/10 px-3 py-1.5 hover:bg-white/20" @click="$refs.dialog.close()">{{ __('forms.close_image') }}</button>
            <img :src="opened?.url" :alt="opened?.name" class="max-h-[73vh] max-w-full rounded-lg object-contain">
        </div>
    </dialog>
    @endunless
</div>
