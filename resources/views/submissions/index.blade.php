@extends('layouts.app')
@section('title', $form->title)
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a> / Respostas @endsection
@section('actions')
    @can('responses.export', $form)
        <a href="{{ route('submissions.export', $form) }}?{{ http_build_query(request()->query()) }}" class="button is-small"><i class="fa-solid fa-file-csv mr-2"></i> Exportar CSV</a>
    @endcan
    @include('partials.form-actions')
@endsection

@section('content')
@include('partials.form-header')
@php
    use App\Services\Tracking\ChannelClassifier;
    $view = request('view', 'inbox');
    $tab = fn ($v) => request()->fullUrlWithQuery(['view' => $v === 'inbox' ? null : $v, 'page' => null]);
@endphp

<div class="is-flex is-flex-wrap-wrap is-align-items-center mb-4" style="gap:.75rem">
    <div class="tabs is-toggle is-small mb-0">
        <ul>
            <li class="{{ $view === 'inbox' ? 'is-active' : '' }}"><a href="{{ $tab('inbox') }}"><i class="fa-solid fa-inbox mr-1"></i> Todas <span class="tag is-rounded is-light ml-2">{{ $counts['inbox'] }}</span></a></li>
            <li class="{{ $view === 'starred' ? 'is-active' : '' }}"><a href="{{ $tab('starred') }}"><i class="fa-solid fa-star mr-1"></i> Favoritas <span class="tag is-rounded is-light ml-2">{{ $counts['starred'] }}</span></a></li>
            <li class="{{ $view === 'spam' ? 'is-active' : '' }}"><a href="{{ $tab('spam') }}"><i class="fa-solid fa-ban mr-1"></i> Spam <span class="tag is-rounded is-light ml-2">{{ $counts['spam'] }}</span></a></li>
            <li><a href="{{ route('submissions.abandoned', $form) }}"><i class="fa-solid fa-person-walking-arrow-right mr-1"></i> Parciais <span class="tag is-rounded is-warning is-light ml-2">{{ $counts['abandoned'] }}</span></a></li>
        </ul>
    </div>
</div>

<form method="GET" class="filter-bar">
    @if ($view !== 'inbox')<input type="hidden" name="view" value="{{ $view }}">@endif
    <div class="control has-icons-left"><input class="input is-small" name="q" value="{{ request('q') }}" placeholder="Buscar nas respostas…" style="width:220px"><span class="icon is-left is-small"><i class="fa-solid fa-magnifying-glass"></i></span></div>
    <input type="date" class="input is-small" name="from" value="{{ request('from') }}" style="width:140px" title="De">
    <input type="date" class="input is-small" name="to" value="{{ request('to') }}" style="width:140px" title="Até">
    <div class="select is-small"><select name="channel"><option value="">Canal: todos</option>@foreach ($options['channel'] as $k => $l)<option value="{{ $k }}" @selected(request('channel') === $k)>{{ $l }}</option>@endforeach</select></div>
    @if ($options['utm_source']->count())<div class="select is-small"><select name="utm_source"><option value="">Origem: todas</option>@foreach ($options['utm_source'] as $v)<option @selected(request('utm_source') === $v)>{{ $v }}</option>@endforeach</select></div>@endif
    @if ($options['utm_campaign']->count())<div class="select is-small"><select name="utm_campaign"><option value="">Campanha: todas</option>@foreach ($options['utm_campaign'] as $v)<option @selected(request('utm_campaign') === $v)>{{ $v }}</option>@endforeach</select></div>@endif
    <div class="select is-small"><select name="device_type"><option value="">Dispositivo: todos</option>@foreach ($options['device_type'] as $v)<option value="{{ $v }}" @selected(request('device_type') === $v)>{{ \App\Http\Controllers\ReportController::deviceLabel($v) }}</option>@endforeach</select></div>
    <button class="button is-small is-primary">Filtrar</button>
    @if (request()->hasAny(['q', 'from', 'to', 'channel', 'utm_source', 'utm_campaign', 'device_type']))<a href="{{ route('submissions.index', $form) }}{{ $view !== 'inbox' ? '?view='.$view : '' }}" class="button is-small is-white">Limpar</a>@endif
</form>

@if ($submissions->isEmpty())
    <div class="box empty">
        <div class="icon-big"><i class="fa-solid fa-inbox"></i></div>
        <h3>Nenhuma resposta {{ request()->hasAny(['q', 'channel', 'from']) ? 'encontrada' : 'ainda' }}</h3>
        <p>{{ request()->hasAny(['q', 'channel', 'from']) ? 'Tente ajustar os filtros.' : 'Assim que alguém responder, a resposta aparece aqui com origem, dispositivo e jornada completa.' }}</p>
        <a href="{{ route('forms.share', $form) }}" class="button is-primary">Compartilhar formulário</a>
    </div>
@else
    <div class="box p-0">
        <div class="table-wrap">
            <table class="table is-fullwidth is-hoverable mb-0">
                <thead><tr>
                    <th style="width:36px"></th>
                    @foreach ($columns as $c)<th>{{ \Illuminate\Support\Str::limit($c->label, 28) }}</th>@endforeach
                    <th>Origem</th><th>Dispositivo</th><th class="has-text-right">Enviada</th>
                </tr></thead>
                <tbody>
                @foreach ($submissions as $sub)
                    @php $s = $sub->session; $link = route('submissions.show', [$form, $sub]); @endphp
                    <tr class="is-clickable" onclick="if (!event.target.closest('button,a')) location='{{ $link }}'">
                        <td x-data="{ on: @js($sub->starred) }">
                            @can('responses.manage', $form)
                                <button class="button is-small is-white" @click="on = !on; FP.api('{{ route('submissions.update', [$form, $sub]) }}', 'PATCH', { starred: on })" title="Favoritar">
                                    <i :class="on ? 'fa-solid fa-star has-text-warning' : 'fa-regular fa-star has-text-grey-light'"></i></button>
                            @else
                                <i class="{{ $sub->starred ? 'fa-solid fa-star has-text-warning' : '' }}"></i>
                            @endcan
                        </td>
                        @foreach ($columns as $c)
                            <td style="max-width:220px"><span class="truncate {{ $loop->first ? 'has-text-weight-semibold' : '' }}">{{ $c->display($sub->data[$c->key] ?? null) }}</span></td>
                        @endforeach
                        <td class="nowrap">
                            @if ($s)
                                <span class="legend-dot" style="background: {{ ChannelClassifier::COLORS[$s->channel] ?? '#ccc' }}"></span>{{ ChannelClassifier::label($s->channel) }}
                                @if ($s->utm_campaign)<br><span class="is-size-8 has-text-muted">{{ \Illuminate\Support\Str::limit($s->utm_campaign, 24) }}</span>@endif
                            @else <span class="has-text-muted">—</span> @endif
                        </td>
                        <td class="nowrap has-text-muted is-size-7">@if ($s)<i class="fa-solid {{ $s->deviceIcon() }} mr-1"></i> {{ $s->os_name }}<br>{{ $s->browser_name }}@endif</td>
                        <td class="has-text-right nowrap is-size-7" title="{{ $sub->submitted_at->setTimezone($account->timezone)->format('d/m/Y H:i:s') }}">
                            {{ $sub->submitted_at->setTimezone($account->timezone)->format('d/m H:i') }}
                            @if ($sub->is_spam)<br><span class="tag is-danger is-light is-size-8">{{ $sub->spam_reason ?? 'spam' }}</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    {{ $submissions->links() }}
@endif
@endsection
