<x-staff-page :title="__('forms.create')">
    <form method="POST" action="{{ route('forms.store') }}" class="panel grid max-w-2xl gap-5">@csrf
        <label class="grid gap-2 text-sm">{{ __('forms.name') }}<input class="input" name="name" value="{{ old('name') }}" required maxlength="255"></label>
        <label class="grid gap-2 text-sm">{{ __('forms.doctor') }}<select class="input" name="doctor_id" required>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}">{{ $doctor->user->name }}</option>@endforeach</select></label>
        <button class="button">{{ __('forms.create') }}</button>
    </form>
</x-staff-page>
