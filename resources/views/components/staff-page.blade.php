@props(['title', 'createRoute' => null])
<x-layout :title="$title">
    <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">{{ $title }}</h1>
        @if($createRoute)<a class="button" href="{{ route($createRoute) }}">{{ __('staff.create') }}</a>@endif
    </div>
    @if(session('status'))<p class="mb-5 rounded-xl bg-teal-50 p-4 text-sm text-teal-800 dark:bg-teal-950 dark:text-teal-200" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<ul class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-200" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    {{ $slot }}
</x-layout>
