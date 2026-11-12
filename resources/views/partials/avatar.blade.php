{{-- Avatar do usuário: foto, se houver, ou iniciais. Params: $u (User), $class? --}}
@if ($u && $u->avatar_path)
    <img src="{{ $u->avatarUrl() }}" alt="{{ $u->name }}" class="avatar {{ $class ?? '' }}" style="object-fit:cover">
@else
    <span class="avatar {{ $class ?? '' }}" aria-hidden="true">{{ $u?->initials() ?? '?' }}</span>
@endif
