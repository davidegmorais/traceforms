@extends('layouts.app')
@section('title', 'Equipe')
@section('crumb'){{ $account->name }}@endsection

@section('content')
@php
    use App\Enums\Role;
    $canManage = $membership->can('members.manage');
    $traceLevels = ['admin' => 'admin', 'gestor' => 'gestor', 'usuario' => 'usuário', 'cliente' => 'cliente'];
@endphp
<div class="columns is-variable is-4">
    <div class="column is-8">
        <div class="box p-0">
            <div class="p-4 is-flex is-align-items-center" style="border-bottom:1px solid var(--tf-line)">
                <span class="box-title mb-0">Membros <span class="tag is-rounded is-light ml-1">{{ $members->count() }}</span></span>
            </div>
            <table class="table is-fullwidth mb-0">
                <tbody>
                @foreach ($members as $m)
                    @php
                        $isMe = $m->user_id === auth()->id();
                        $editable = $canManage && $m->role->scopable();
                    @endphp
                    <tr x-data="{ edit: false, restrict: {{ $m->isRestricted() ? 'true' : 'false' }} }">
                        <td style="width:52px">@include('partials.avatar', ['u' => $m->user])</td>
                        <td>
                            <strong>{{ $m->user->name }}</strong> @if ($isMe)<span class="tag is-light is-size-8">você</span>@endif<br>
                            <span class="is-size-7 has-text-muted">{{ $m->user->email }}</span>
                            @if ($m->isRestricted())
                                <br><span class="is-size-8 has-text-warning-dark"><i class="fa-solid fa-lock mr-1"></i> Restrito a {{ count($m->form_ids) }} formulário(s)</span>
                            @endif
                            @if (! $m->user->trace_id)
                                <br><span class="is-size-8 has-text-muted">Ainda não entrou pelo Trace ID</span>
                            @endif

                            @if ($editable)
                                <form method="POST" action="{{ route('members.scope', $m) }}" x-show="edit" x-cloak class="mt-3 p-3" style="background:var(--tf-canvas);border-radius:10px;border:1px solid var(--tf-line)">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="restrict" value="0">
                                    <label class="checkbox is-size-7"><input type="checkbox" name="restrict" value="1" x-model="restrict"> Limitar a formulários específicos</label>
                                    <div x-show="restrict" class="mt-2" style="max-height:180px;overflow:auto">
                                        @forelse ($forms as $f)
                                            <label class="checkbox is-size-7 is-block mb-1"><input type="checkbox" name="form_ids[]" value="{{ $f->id }}" @checked(in_array($f->id, $m->form_ids ?? []))> {{ $f->title }}</label>
                                        @empty
                                            <p class="is-size-7 has-text-muted">Nenhum formulário ainda.</p>
                                        @endforelse
                                    </div>
                                    <div class="buttons mt-3 mb-0"><button class="button is-small is-primary">Salvar formulários</button><button type="button" class="button is-small" @click="edit = false">Cancelar</button></div>
                                </form>
                            @endif
                        </td>
                        <td class="has-text-right nowrap" style="vertical-align:top;padding-top:1rem">
                            <span class="tag {{ $m->role->color() }}">{{ $m->role->label() }}</span>
                            @if ($editable)
                                <button class="button is-small is-white ml-1" @click="edit = !edit" title="Escolher formulários"><i class="fa-solid fa-lock has-text-muted"></i></button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="column is-4">
        <div class="box">
            <div class="box-title">Como dar acesso</div>
            <p class="is-size-7 mb-3">Quem entra, com qual nível e quem sai é definido no <strong>Trace ID</strong>. A pessoa aparece aqui depois do primeiro login no TraceForms.</p>
            <a href="{{ config('traceid.profile_url') }}" target="_blank" rel="noopener" class="button is-small is-fullwidth mb-4">Gerenciar acessos no Trace ID <i class="fa-solid fa-arrow-up-right-from-square ml-2 is-size-8"></i></a>
            <table class="table is-fullwidth is-narrow is-size-7 mb-0">
                <thead><tr><th>Nível no Trace ID</th><th>Papel aqui</th></tr></thead>
                <tbody>
                @foreach ($traceLevels as $level => $label)
                    <tr><td>{{ $label }}</td><td>{{ \App\Services\TraceId\TraceIdSync::roleFor($level)->label() }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="box">
            <div class="box-title">O que cada papel faz</div>
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
            <p class="help">Editores, analistas e leitores podem ser limitados a formulários específicos, o que é útil para agências e clientes.</p>
        </div>
    </div>
</div>
@endsection
