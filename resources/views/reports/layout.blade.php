@extends('layouts.app')
@section('title', $form->title)
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a> / Relatórios @endsection
@section('actions')@include('partials.form-actions')@endsection

@section('content')
@include('partials.form-header')
@php
    $tabs = [
        'overview' => ['Visão geral', 'fa-gauge'],
        'acquisition' => ['Aquisição & UTMs', 'fa-bullhorn'],
        'audience' => ['Audiência & dispositivos', 'fa-users'],
        'behavior' => ['Comportamento & funil', 'fa-filter'],
        'questions' => ['Respostas por pergunta', 'fa-square-poll-vertical'],
    ];
@endphp
<div class="is-flex is-flex-wrap-wrap is-align-items-center mb-4" style="gap:.75rem">
    <div class="buttons has-addons mb-0">
        @foreach ($tabs as $key => [$label, $icon])
            <a href="{{ route('reports.show', [$form, $key]) }}?{{ http_build_query($filters->query()) }}" class="button is-small {{ $tab === $key ? 'is-primary is-selected' : '' }}"><i class="fa-solid {{ $icon }} mr-2"></i> {{ $label }}</a>
        @endforeach
    </div>
</div>
@include('partials.filters', ['filters' => $filters, 'segmentOptions' => $segmentOptions])

@yield('report')
@endsection
