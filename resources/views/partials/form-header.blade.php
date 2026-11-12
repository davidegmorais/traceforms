{{-- Abas de navegação de um formulário --}}
@php $is = fn (...$p) => request()->routeIs(...$p) ? 'is-active' : ''; @endphp
<nav class="fp-tabs">
    @can('forms.edit', $form)
        <a href="{{ route('forms.edit', $form) }}" class="{{ $is('forms.edit') }}"><i class="fa-solid fa-pen-ruler"></i> Construtor</a>
        <a href="{{ route('forms.settings', $form) }}" class="{{ $is('forms.settings') }}"><i class="fa-solid fa-sliders"></i> Configurações</a>
    @endcan
    <a href="{{ route('forms.share', $form) }}" class="{{ $is('forms.share') }}"><i class="fa-solid fa-share-nodes"></i> Compartilhar & UTMs</a>
    <a href="{{ route('reports.show', $form) }}" class="{{ $is('reports.*') }}"><i class="fa-solid fa-chart-line"></i> Relatórios</a>
    @can('responses.view', $form)
        <a href="{{ route('submissions.index', $form) }}" class="{{ $is('submissions.*') }}"><i class="fa-solid fa-inbox"></i> Respostas</a>
    @endcan
</nav>
