@props(['model', 'label'])
<div x-data="clinicTimePicker" class="relative grid gap-2 text-sm" @keydown.escape.stop="open=false" @time-selected.stop="{{ $model }} = $event.detail">
    <label>{{ $label }}<input class="input mt-2 w-full" dir="ltr" inputmode="numeric" placeholder="HH:MM" maxlength="5" x-model="{{ $model }}" @input="typed($event)"></label>
    <button type="button" class="justify-self-start text-xs text-teal-700 dark:text-teal-300" @click="show({{ $model }})" :aria-expanded="open">انتخاب ساعت</button>
    <p x-show="error && !open" class="text-xs text-red-600" role="alert" x-text="error"></p>
    <div x-show="open" x-cloak @click.outside="open=false" class="absolute top-full z-30 grid w-64 max-w-[85vw] gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-label="انتخاب ساعت ۲۴ ساعته">
        <div class="flex items-center gap-2" dir="ltr"><select x-ref="hour" class="input min-w-0 flex-1" x-model="hour" aria-label="ساعت"><template x-for="h in 24"><option :value="String(h-1).padStart(2,'0')" x-text="String(h-1).padStart(2,'0')"></option></template></select><span>:</span><select class="input min-w-0 flex-1" x-model="minute" aria-label="دقیقه"><template x-for="m in 60"><option :value="String(m-1).padStart(2,'0')" x-text="String(m-1).padStart(2,'0')"></option></template></select></div>
        <output class="text-center text-xl font-bold" dir="ltr" x-text="`${hour}:${minute}`"></output><div class="flex flex-wrap gap-2"><template x-for="h in ['08','09','12','16','20','22']"><button type="button" class="rounded-lg bg-slate-100 p-2 dark:bg-slate-800" @click="hour=h;minute='00'" x-text="h+':00'"></button></template></div><p class="text-red-600" x-text="error"></p><div class="flex gap-3"><button type="button" class="button" @click="apply">تأیید</button><button type="button" @click="open=false">لغو</button></div>
    </div>
</div>
