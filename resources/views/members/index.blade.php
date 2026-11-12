@extends('layouts.app')
@section('title', 'Equipe')
@section('crumb'){{ $account->name }}@endsection

@section('content')
@php use App\Enums\Role; $canManage = $membership->can('members.manage'); @endphp
<div class="columns is-variable is-4">
    <div class="column is-8">
        @if (session('invite_link'))
            <div class="notification is-success is-light">
                <p class="mb-2"><strong>Convite criado.</strong> Se o e-mail não chegar, envie este link diretamente:</p>
                <div class="field has-addons mb-0">
                    <div class="control is-expanded"><input class="input is-small mono" readonly value="{{ session('invite_link') }}"></div>
                    <div class="control"><button class="button is-small" onclick="FP.copy(@js(session('invite_link')), 'Link do convite')"><i class="fa-regular fa-copy"></i></button></div>
                </div>
            </div>
        @endif

        <div class="box p-0">
            <div class="p-4 is-flex is-align-items-center" style="border-bottom:1px solid var(--fp-border)">
                <span class="box-title mb-0"><i class="fa-solid fa-user-group has-text-primary"></i> Membros <span class="tag is-rounded is-light ml-1">{{ $members->count() }}</span></span>
            </div>
            <table class="table is-fullwidth mb-0">
                <tbody>
                @foreach ($members as $m)
                    @php
                        $isMe = $m->user_id === auth()->id();
                        $editable = $canManage && ! $isMe && $m->role !== Role::Owner && ($membership->role === Role::Owner || $m->role->level() < $membership->role->level());
                    @endphp
                    <tr x-data="{ edit: false, role: '{{ $m->role->value }}', restrict: {{ $m->isRestricted() ? 'true' : 'false' }} }">
                        <td style="width:52px">@include('partials.avatar', ['u' => $m->user])</td>
                        <td>
                            <strong>{{ $m->user->name }}</strong> @if ($isMe)<span class="tag is-light is-size-8">você</span>@endif<br>
                            <span class="is-size-7 has-text-muted">{{ $m->user->email }}</span>
                            @if ($m->isRestricted())
                                <br><span class="is-size-8 has-text-warning-dark"><i class="fa-solid fa-lock mr-1"></i> Restrito a {{ count($m->form_ids) }} formulário(s)</span>
                            @endif

                            @if ($editable)
                                <form method="POST" action="{{ route('members.update', $m) }}" x-show="edit" x-cloak class="mt-3 p-3" style="background:#f3f5f4;border-radius:10px;border:1px solid var(--fp-border)">
                                    @csrf @method('PUT')
                                    <div class="field">
                                        <label class="label is-small">Papel</label>
                                        <div class="select is-small"><select name="role" x-model="role">
                                            @foreach ($assignable as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
                                        </select></div>
                                    </div>
                                    <div x-show="['editor','analyst','viewer'].includes(role)">
                                        <input type="hidden" name="restrict" value="0">
                                        <label class="checkbox is-size-7"><input type="checkbox" name="restrict" value="1" x-model="restrict"> Limitar a formulários específicos</label>
                                        <div x-show="restrict" class="mt-2" style="max-height:180px;overflow:auto">
                                            @forelse ($forms as $f)
                                                <label class="checkbox is-size-7 is-block mb-1"><input type="checkbox" name="form_ids[]" value="{{ $f->id }}" @checked(in_array($f->id, $m->form_ids ?? []))> {{ $f->title }}</label>
                                            @empty
                                                <p class="is-size-7 has-text-muted">Nenhum formulário ainda.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="buttons mt-3 mb-0"><button class="button is-small is-primary">Salvar</button><button type="button" class="button is-small" @click="edit = false">Cancelar</button></div>
                                </form>
                            @endif
                        </td>
                        <td class="has-text-right nowrap" style="vertical-align:top;padding-top:1rem">
                            <span class="tag {{ $m->role->color() }}">{{ $m->role->label() }}</span>
                            @if ($editable)
                                <button class="button is-small is-white ml-1" @click="edit = !edit" title="Alterar acesso"><i class="fa-solid fa-pen"></i></button>
                                <form method="POST" action="{{ route('members.destroy', $m) }}" class="is-inline" onsubmit="return confirm('Remover {{ addslashes($m->user->name) }} da conta?')">@csrf @method('DELETE')
                                    <button class="button is-small is-white" title="Remover"><i class="fa-solid fa-user-minus has-text-danger"></i></button></form>
                            @elseif ($isMe && $m->role !== Role::Owner)
                                <form method="POST" action="{{ route('members.destroy', $m) }}" class="is-inline" onsubmit="return confirm('Sair desta conta?')">@csrf @method('DELETE')
                                    <button class="button is-small is-white" title="Sair da conta"><i class="fa-solid fa-arrow-right-from-bracket"></i></button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($invitations->count())
            <div class="box p-0">
                <div class="p-4" style="border-bottom:1px solid var(--fp-border)"><span class="box-title mb-0"><i class="fa-regular fa-envelope has-text-primary"></i> Convites pendentes</span></div>
                <table class="table is-fullwidth mb-0">
                    @foreach ($invitations as $inv)
                        <tr>
                            <td><strong>{{ $inv->email }}</strong><br><span class="is-size-7 has-text-muted">por {{ $inv->inviter?->name ?? '—' }} · {{ $inv->expires_at->isPast() ? 'expirado' : 'expira '.$inv->expires_at->locale('pt_BR')->diffForHumans() }}</span></td>
                            <td class="has-text-right nowrap">
                                <span class="tag {{ $inv->role->color() }}">{{ $inv->role->label() }}</span>
                                <button class="button is-small is-white" onclick="FP.copy(@js(route('invitations.show', $inv->token)), 'Link do convite')" title="Copiar link"><i class="fa-regular fa-copy"></i></button>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('invitations.revoke', $inv) }}" class="is-inline">@csrf @method('DELETE')<button class="button is-small is-white" title="Cancelar"><i class="fa-solid fa-xmark"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    <div class="column is-4">
        @if ($canManage)
            <div class="box" x-data="{ role: 'editor' }">
                <div class="box-title"><i class="fa-solid fa-user-plus has-text-primary"></i> Convidar pessoa</div>
                <form method="POST" action="{{ route('members.invite') }}">
                    @csrf
                    <div class="field"><label class="label is-small">E-mail</label><input class="input" type="email" name="email" required placeholder="pessoa@empresa.com"></div>
                    <div class="field">
                        <label class="label is-small">Papel</label>
                        @foreach ($assignable as $r)
                            <label class="is-block p-2 mb-1 is-clickable" style="border-radius:9px;border:1.5px solid" :style="role === '{{ $r->value }}' ? 'border-color:#0e7c66;background:#f3f8f6' : 'border-color:#dce3e0'">
                                <input type="radio" name="role" value="{{ $r->value }}" x-model="role" class="mr-1"> <strong class="is-size-7">{{ $r->label() }}</strong>
                                <span class="is-block is-size-8 has-text-muted ml-4">{{ $r->description() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button class="button is-primary is-fullwidth"><i class="fa-regular fa-paper-plane mr-2"></i> Enviar convite</button>
                </form>
            </div>
        @endif

        <div class="box">
            <div class="box-title"><i class="fa-solid fa-shield-halved has-text-primary"></i> Níveis de acesso</div>
            <div class="table-wrap">
            <table class="table is-fullwidth is-narrow perm-table is-size-8">
                <thead><tr><th></th>@foreach (array_reverse($roles) as $r)<th class="has-text-centered" title="{{ $r->description() }}">{{ mb_substr($r->label(), 0, 5) }}.</th>@endforeach</tr></thead>
                <tbody>
                @foreach (Role::ABILITY_LABELS as $ability => $label)
                    <tr><td>{{ $label }}</td>@foreach (array_reverse($roles) as $r)<td><i class="fa-solid {{ $r->can($ability) ? 'fa-check' : 'fa-minus' }}"></i></td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
            </div>
            <p class="help">Leitores, analistas e editores podem ser limitados a formulários específicos — útil para agências e clientes.</p>
        </div>
    </div>
</div>
@endsection
