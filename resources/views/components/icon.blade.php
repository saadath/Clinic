@props(['name' => 'cross'])
<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('home') <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/> @break
        @case('settings') <path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="currentColor"/><circle cx="15" cy="17" r="3" fill="currentColor"/> @break
        @case('arrow') <path d="M7 17 17 7M7 7h10v10"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('shield') <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/> @break
        @case('logout') <path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/> @break
        @case('users') <circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 11h18M8 15h2M14 15h2"/> @break
        @case('file') <path d="M14 3H5v18h14V8Zm0 0v5h5M8 12h8M8 16h6"/> @break
        @case('undo') <path d="M3 7v6h6M4 13a8 8 0 1 1 2.3 5.7"/><path d="m3 13 5-5"/> @break
        @case('redo') <path d="M21 7v6h-6M20 13a8 8 0 1 0-2.3 5.7"/><path d="m21 13-5-5"/> @break
        @case('bold') <path d="M7 4h6a4 4 0 0 1 0 8H7zM7 12h7a4 4 0 0 1 0 8H7z"/> @break
        @case('italic') <path d="M19 4h-9M14 20H5M15 4 9 20"/> @break
        @case('underline') <path d="M7 4v6a5 5 0 0 0 10 0V4M5 20h14"/> @break
        @case('strikethrough') <path d="M5 12h14M17 6c-.8-1.3-2.2-2-5-2-3 0-5 1.5-5 4 0 1.8 1.4 3 3.5 4M7 18c1 1.3 2.5 2 5 2 3.3 0 5-1.4 5-4 0-1.8-1.2-3-3.2-4"/> @break
        @case('list-bullets') <path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/> @break
        @case('list-numbers') <path d="M10 6h11M10 12h11M10 18h11M4 5h1v3M4 11h2l-2 2h2M4 17h2l-2 2h2"/> @break
        @case('align-left') <path d="M4 5h16M4 9h10M4 13h16M4 17h10M4 21h16"/> @break
        @case('align-center') <path d="M4 5h16M7 9h10M4 13h16M7 17h10M4 21h16"/> @break
        @case('align-right') <path d="M4 5h16M10 9h10M4 13h16M10 17h10M4 21h16"/> @break
        @case('direction-rtl') <path d="M4 6h10M4 12h16M4 18h10"/><path d="m20 12-5-5v10z"/> @break
        @case('direction-ltr') <path d="M10 6h10M4 12h16M10 18h10"/><path d="m4 12 5-5v10z"/> @break
        @case('link') <path d="M10 13a5 5 0 0 0 7.1 0l3-3A5 5 0 0 0 13 2.9l-1.7 1.7M14 11a5 5 0 0 0-7.1 0l-3 3A5 5 0 0 0 11 21.1l1.7-1.7"/> @break
        @case('table') <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16M15 4v16"/> @break
        @case('clear-format') <path d="m4 7 8-4 8 4M5 10l7 11 7-11M3 3l18 18"/> @break
        @case('paragraph') <path d="M13 4h7M13 8h7M13 12h7M13 16h7M13 20h7"/><path d="M10 4v16M10 4H7a4 4 0 0 0 0 8h3"/> @break
        @case('save') <path d="M5 3h12l4 4v14H3V3zM7 3v6h10V3M7 21v-8h10v8"/> @break
        @case('printer') <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6zM6 12h.01"/> @break
        @case('trash') <path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"/> @break
        @case('cross') <path d="m6 6 12 12M18 6 6 18"/> @break
        @case('stethoscope') <path d="M11 2v2M5 2v2M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/> @break
        @case('pencil') <path d="m15 5 4 4M4 20l1-5L16 4a2.8 2.8 0 0 1 4 4L9 19z"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('check') <path d="m5 13 4 4L19 7"/> @break
        @case('trend') <path d="m3 17 6-6 4 4 7-7"/><path d="M14 8h6v6"/> @break
        @case('user-plus') <circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M19 8v6M16 11h6"/> @break
        @case('open') <path d="M14 4h6v6M20 4l-9 9M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/> @break
        @default <path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7Z"/>
    @endswitch
</svg>
