@extends('layouts.app')
@section('title', 'Meu perfil')

@section('content')
<div class="columns is-variable is-5" style="max-width:1080px">
    <div class="column is-7">

        {{-- Foto --}}
        <section class="box profile-photo" x-data="{ preview: null }">
            <div class="box-title">Foto</div>
            <div class="is-flex is-align-items-center" style="gap:1.4rem">
                <div class="photo-frame">
                    <template x-if="preview"><img :src="preview" alt="Prévia da nova foto" class="avatar is-xl"></template>
                    <template x-if="!preview">@include('partials.avatar', ['u' => $user, 'class' => 'is-xl'])</template>
                </div>
                <div>
                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="is-flex is-flex-wrap-wrap" style="gap:.5rem">
                        @csrf
                        <label class="button is-small">
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="is-hidden" x-ref="file"
                                   @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); }">
                            {{ $user->avatar_path ? 'Trocar foto' : 'Escolher foto' }}
                        </label>
                        <button class="button is-small is-primary" x-show="preview" x-cloak>Salvar foto</button>
                        <button type="button" class="button is-small is-white" x-show="preview" x-cloak @click="preview = null; $refs.file.value = ''">Cancelar</button>
                    </form>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.remove') }}" class="mt-2" x-show="!preview">@csrf @method('DELETE')
                            <button class="button is-small is-white has-text-danger px-0">Remover foto</button>
                        </form>
                    @endif
                    <p class="help mt-2">JPG, PNG ou WebP de até 4 MB. A imagem é cortada em quadrado a partir do centro.</p>
                    @error('avatar')<p class="help is-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Dados --}}
        <section class="box">
            <div class="box-title">Dados pessoais</div>
            <dl class="kv mb-4">
                <dt>Nome</dt><dd>{{ $user->name }}</dd>
                <dt>E-mail</dt><dd>{{ $user->email }}</dd>
            </dl>
            <p class="is-size-7 has-text-muted mb-3">Nome, e-mail e senha são os do seu login no Trace ID e são atualizados aqui a cada entrada.</p>
            <a href="{{ config('traceid.profile_url') }}" target="_blank" rel="noopener" class="button is-small">Alterar no Trace ID <i class="fa-solid fa-arrow-up-right-from-square ml-2 is-size-8"></i></a>
        </section>
    </div>

    <div class="column is-5">
        <section class="box">
            <div class="box-title">Suas empresas</div>
            @foreach ($accounts as $acc)
                <div class="is-flex is-align-items-center py-2" style="gap:.6rem;border-bottom:1px solid var(--tf-line)">
                    <span class="avatar is-small" style="background: {{ $acc->brand_color }}">{{ mb_strtoupper(mb_substr($acc->name, 0, 1)) }}</span>
                    <span style="flex:1">{{ $acc->name }}</span>
                    @if ($acc->id === $account->id)
                        <span class="tag {{ $acc->pivot->role->color() }}">{{ $acc->pivot->role->label() }}</span>
                    @elseif ($acc->trace_account_id)
                        <form method="POST" action="{{ route('accounts.switch', $acc) }}">@csrf<button class="button is-small">Entrar</button></form>
                    @endif
                </div>
            @endforeach
            <p class="help mt-3">Outras empresas aparecem aqui depois do primeiro login nelas pelo Trace ID.</p>
        </section>

        @if ($sessions->count())
            <section class="box" x-data="{ open: false }">
                <div class="box-title">Dispositivos conectados</div>
                @foreach ($sessions as $sess)
                    @php
                        $ua = (string) $sess->user_agent;
                        $isMe = $sess->id === session()->getId();
                        $label = str_contains($ua, 'iPhone') ? 'iPhone' : (str_contains($ua, 'Android') ? 'Android' : (str_contains($ua, 'Mac') ? 'Mac' : (str_contains($ua, 'Windows') ? 'Windows' : (str_contains($ua, 'Linux') ? 'Linux' : 'Dispositivo'))));
                        $br = str_contains($ua, 'Edg/') ? 'Edge' : (str_contains($ua, 'Firefox') ? 'Firefox' : (str_contains($ua, 'Chrome') ? 'Chrome' : (str_contains($ua, 'Safari') ? 'Safari' : 'Navegador')));
                    @endphp
                    <div class="is-flex is-align-items-center py-2 is-size-7" style="gap:.6rem;border-bottom:1px solid var(--tf-line)">
                        <i class="fa-solid {{ in_array($label, ['iPhone', 'Android']) ? 'fa-mobile-screen' : 'fa-laptop' }} has-text-muted"></i>
                        <span style="flex:1"><strong>{{ $label }} · {{ $br }}</strong><br><span class="has-text-muted">{{ $sess->ip_address }} · {{ \Carbon\Carbon::createFromTimestamp($sess->last_activity)->locale('pt_BR')->diffForHumans() }}</span></span>
                        @if ($isMe)<span class="tag is-success is-light">Este</span>@endif
                    </div>
                @endforeach
                @if ($sessions->count() > 1)
                    <button type="button" class="button is-small is-fullwidth mt-3" @click="open = true" x-show="!open">Sair dos outros dispositivos</button>
                    <form method="POST" action="{{ route('profile.logout-others') }}" x-show="open" x-cloak class="mt-3">
                        @csrf
                        <p class="is-size-7 mb-2">Os outros dispositivos vão precisar entrar de novo pelo Trace ID.</p>
                        <button class="button is-small is-danger is-light">Confirmar</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</div>
@endsection
