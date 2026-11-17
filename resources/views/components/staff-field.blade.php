@props(['name', 'value' => '', 'type' => 'text', 'required' => false])
<label class="flex flex-col gap-2 text-sm">
    <span>{{ __('staff.'.$name) }}</span>
    @if($type === 'password')
        <span class="relative flex" x-data="{ visible: false }">
            <input class="input w-full pe-12" name="{{ $name }}" type="password" :type="visible ? 'text' : 'password'" value="" @required($required) {{ $attributes }} @if($errors->has($name)) aria-invalid="true" @endif>
            <button type="button" class="absolute inset-y-0 end-0 flex w-12 items-center justify-center text-slate-500" @click="visible = !visible" :aria-pressed="visible" aria-label="{{ __('staff.toggle_password') }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path x-show="visible" d="m3 3 18 18"/></svg></button>
        </span>
    @else
        <input class="input" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) {{ $attributes }} @if($errors->has($name)) aria-invalid="true" @endif>
    @endif
    @error($name)<span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
</label>
