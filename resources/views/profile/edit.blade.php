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
        <form method="POST" action="{{ route('profile.update') }}" class="box">
            @csrf @method('PUT')
            <div class="box-title">Dados pessoais</div>
            <div class="field">
                <label class="label" for="name">Nome</label>
                <input class="input @error('name') is-danger @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                @error('name')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="email">E-mail</label>
                <input class="input @error('email') is-danger @enderror" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                @error('email')<p class="help is-danger">{{ $message }}</p>@enderror
                <p class="help">É o e-mail que você usa para entrar e para receber convites.</p>
            </div>
            <button class="button is-primary">Salvar dados</button>
        </form>

        {{-- Senha --}}
        <form method="POST" action="{{ route('profile.password') }}" class="box" x-data="{ show: false }">
            @csrf @method('PUT')
            <div class="box-title">Senha</div>
            <div class="field">
                <label class="label" for="current_password">Senha atual</label>
                <input class="input @error('current_password') is-danger @enderror" id="current_password" :type="show ? 'text' : 'password'" name="current_password" required autocomplete="current-password">
                @error('current_password')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>
            <div class="columns mb-0">
                <div class="column field mb-0">
                    <label class="label" for="password">Nova senha</label>
                    <input class="input @error('password') is-danger @enderror" id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password">
                </div>
                <div class="column field">
                    <label class="label" for="password_confirmation">Repita a nova senha</label>
                    <input class="input" id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
            @error('password')<p class="help is-danger mb-2">{{ $message }}</p>@enderror
            <p class="help mb-3">Pelo menos 8 caracteres, com letras e números.</p>
            <label class="checkbox is-size-7 mr-4"><input type="checkbox" @change="show = $event.target.checked"> Mostrar senhas</label>
            <label class="checkbox is-size-7"><input type="checkbox" name="logout_others" value="1" checked> Sair dos outros dispositivos</label>
            <div class="mt-4"><button class="button is-primary">Alterar senha</button></div>
        </form>
    </div>

    <div class="column is-5">
        <section class="box">
            <div class="box-title">Suas contas</div>
            @foreach ($accounts as $acc)
                <div class="is-flex is-align-items-center py-2" style="gap:.6rem;border-bottom:1px solid var(--tf-line)">
                    <span class="avatar is-small" style="background: {{ $acc->brand_color }}">{{ mb_strtoupper(mb_substr($acc->name, 0, 1)) }}</span>
                    <span style="flex:1">{{ $acc->name }}</span>
                    <span class="tag {{ $acc->pivot->role->color() }}">{{ $acc->pivot->role->label() }}</span>
                </div>
            @endforeach
            <a href="{{ route('accounts.create') }}" class="button is-small is-light is-fullwidth mt-3"><i class="fa-solid fa-plus mr-2"></i> Nova conta</a>
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
                        <div class="field"><label class="label is-small">Confirme sua senha</label><input type="password" class="input is-small" name="password" required autocomplete="current-password"></div>
                        <button class="button is-small is-danger is-light">Sair dos outros dispositivos</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</div>
@endsection
