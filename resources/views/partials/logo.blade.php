{{-- Marca TraceForms: um rastro com três paradas, terminando no ponto de chegada (a resposta). --}}
<svg width="{{ $size ?? 28 }}" height="{{ $size ?? 28 }}" viewBox="0 0 32 32" aria-hidden="true">
    <rect width="32" height="32" rx="9" fill="{{ $bg ?? '#0e7c66' }}"/>
    <path d="M7 22.5 L13 13.5 L19 19 L25.5 9.5" fill="none" stroke="{{ $fg ?? '#fff' }}" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="7" cy="22.5" r="2.2" fill="{{ $fg ?? '#fff' }}"/>
    <circle cx="25.5" cy="9.5" r="3.1" fill="#f2b33d"/>
</svg>
