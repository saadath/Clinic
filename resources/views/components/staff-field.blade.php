@props(['name', 'value' => '', 'type' => 'text', 'required' => false])
<label class="flex flex-col gap-2 text-sm">
    <span>{{ __('staff.'.$name) }}</span>
    <input class="input" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" @required($required) {{ $attributes }} @if($errors->has($name)) aria-invalid="true" @endif>
</label>
