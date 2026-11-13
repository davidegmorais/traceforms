@extends('layouts.app')
@section('title', $form->title)
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a> / <a href="{{ route('submissions.index', $form) }}">Respostas</a> / Parciais @endsection
@section('actions')@include('partials.form-actions')@endsection

@section('content')
@include('partials.form-header')
@php use App\Services\Tracking\ChannelClassifier; $tz = $account->timezone; @endphp
<div class="notification is-warning is-light is-size-7">
    <i class="fa-solid fa-person-walking-arrow-right mr-2"></i>
    Pessoas que <strong>começaram a preencher e saíram sem enviar</strong>. Se deixaram e-mail ou telefone, você pode recuperá-las.
    Respeite o consentimento e a sua política de privacidade (LGPD).
</div>

@if ($sessions->isEmpty())
    <div class="box empty">
        <div class="icon-big"><i class="fa-solid fa-face-smile"></i></div>
        <h3>Nenhuma resposta parcial</h3>
        <p>Ou todo mundo que começou terminou, ou a opção “Salvar respostas parciais” está desativada nas configurações.</p>
    </div>
@else
    @foreach ($sessions as $s)
        <div class="box">
            <div class="is-flex is-justify-content-space-between is-flex-wrap-wrap mb-3" style="gap:.5rem">
                <div class="is-size-7">
                    <strong>{{ $s->last_activity_at?->setTimezone($tz)->format('d/m/Y H:i') }}</strong>
                    · parou em <span class="tag is-danger is-light">{{ \Illuminate\Support\Str::limit(optional($form->fields->firstWhere('key', $s->last_field_key))->label ?? '—', 50) }}</span>
                </div>
                <div class="is-size-7 has-text-muted">
                    <span class="legend-dot" style="background: {{ ChannelClassifier::COLORS[$s->channel] ?? '#ccc' }}"></span>{{ ChannelClassifier::label($s->channel) }}{{ $s->utm_campaign ? ' · '.$s->utm_campaign : '' }}
                    · <i class="fa-solid {{ $s->deviceIcon() }}"></i> {{ $s->os_name }} · {{ \App\Services\Reports\ReportService::duration($s->duration_ms) }}
                </div>
            </div>
            <div class="columns is-multiline is-variable is-2">
                @foreach ($form->fields as $f)
                    @if (array_key_exists($f->key, $s->partial_data ?? []))
                        <div class="column is-4">
                            <p class="is-size-8 has-text-muted has-text-weight-semibold">{{ \Illuminate\Support\Str::limit($f->label, 50) }}</p>
                            <p class="is-size-7 has-text-weight-medium">{{ $f->display($s->partial_data[$f->key]) }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
    {{ $sessions->links() }}
@endif
@endsection
