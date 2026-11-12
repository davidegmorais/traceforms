@extends('layouts.app')
@section('title', 'Novo formulário')
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a>@endsection

@section('content')
<form method="POST" action="{{ route('forms.store') }}" x-data="{ tpl: '{{ old('template', 'lead') }}', title: @js(old('title', '')), names: @js(collect($templates)->map(fn ($t) => $t['name'])), layouts: @js(collect($templates)->map(fn ($t) => $t['layout'])), layout: @js(old('layout', $templates['lead']['layout'])) }" x-effect="layout = layouts[tpl]" style="max-width:1000px">
    @csrf
    <div class="box mb-5">
        <label class="label is-medium" for="title">Como vai se chamar?</label>
        <input id="title" class="input is-medium @error('title') is-danger @enderror" name="title" x-model="title" :placeholder="tpl === 'blank' ? 'Ex.: Pesquisa de satisfação 2026' : names[tpl]" autofocus maxlength="200">
        @error('title')<p class="help is-danger">{{ $message }}</p>@enderror
    </div>

    <h2 class="section-title mt-0"><i class="fa-solid fa-layer-group has-text-primary"></i> Escolha um ponto de partida</h2>
    <input type="hidden" name="template" :value="tpl">
    <div class="columns is-multiline is-variable is-3">
        @foreach ($templates as $key => $t)
            <div class="column is-6-tablet is-4-desktop">
                <div class="tpl" :class="{ 'is-selected': tpl === '{{ $key }}' }" @click="tpl = '{{ $key }}'; if (!title) $refs.go.focus()" role="radio" :aria-checked="tpl === '{{ $key }}'" tabindex="0" @keydown.enter.prevent="tpl = '{{ $key }}'">
                    <div class="is-flex is-justify-content-space-between">
                        <div class="tpl-icon" style="background: {{ $t['color'] }}"><i class="fa-solid {{ $t['icon'] }}"></i></div>
                        <i class="fa-solid fa-circle-check has-text-primary is-size-5" x-show="tpl === '{{ $key }}'"></i>
                    </div>
                    <p class="has-text-weight-bold mb-1">{{ $t['name'] }}</p>
                    <p class="is-size-7 has-text-muted mb-2">{{ $t['description'] }}</p>
                    <p class="is-size-8 has-text-muted">
                        @if (count($t['fields'])){{ count($t['fields']) }} perguntas · @endif
                        {{ \App\Models\Form::LAYOUT_LABELS[$t['layout']][0] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
    <h2 class="section-title"><i class="fa-solid fa-table-cells-large has-text-primary"></i> Escolha o formato</h2>
    <p class="has-text-muted mb-3" style="margin-top:-.5rem">Cada modelo já sugere um formato. Você pode trocar agora ou depois, no construtor (aba Design).</p>
    <input type="hidden" name="layout" :value="layout">
    <div class="layout-picker is-wide">
        @foreach (\App\Models\Form::LAYOUT_LABELS as $key => [$label, $desc])
            <button type="button" class="layout-opt" :class="{ 'is-selected': layout === '{{ $key }}' }" @click.stop="layout = '{{ $key }}'; layouts[tpl] = '{{ $key }}'">
                <span class="layout-mini is-{{ $key }}" aria-hidden="true"><i></i><b></b><b></b><b></b></span>
                <strong>{{ $label }}</strong>
                <small>{{ $desc }}</small>
            </button>
        @endforeach
    </div>

    <div class="is-flex is-justify-content-flex-end mt-5" style="gap:.5rem">
        <a href="{{ route('forms.index') }}" class="button">Cancelar</a>
        <button class="button is-primary" x-ref="go">Criar e abrir o construtor <i class="fa-solid fa-arrow-right ml-2"></i></button>
    </div>
</form>
@endsection
