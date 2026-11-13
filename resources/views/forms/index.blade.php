@extends('layouts.app')
@section('title', 'Formulários')
@section('actions')
    @can('forms.create')
        <a href="{{ route('forms.create') }}" class="button is-primary is-small"><i class="fa-solid fa-plus mr-2"></i> Novo formulário</a>
    @endcan
@endsection

@section('content')
    @php $total = $counts->sum(); $st = request('status'); @endphp
    <div class="is-flex is-flex-wrap-wrap is-align-items-center mb-5" style="gap:.75rem">
        <div class="buttons has-addons mb-0">
            <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="button is-small {{ ! $st ? 'is-primary is-selected' : '' }}">Todos <span class="tag is-rounded ml-2 is-light">{{ $total }}</span></a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'published', 'page' => null]) }}" class="button is-small {{ $st === 'published' ? 'is-primary is-selected' : '' }}">Publicados <span class="tag is-rounded ml-2 is-light">{{ $counts['published'] ?? 0 }}</span></a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'draft', 'page' => null]) }}" class="button is-small {{ $st === 'draft' ? 'is-primary is-selected' : '' }}">Rascunhos <span class="tag is-rounded ml-2 is-light">{{ $counts['draft'] ?? 0 }}</span></a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'closed', 'page' => null]) }}" class="button is-small {{ $st === 'closed' ? 'is-primary is-selected' : '' }}">Encerrados <span class="tag is-rounded ml-2 is-light">{{ $counts['closed'] ?? 0 }}</span></a>
        </div>
        <form method="GET" class="is-flex ml-auto" style="gap:.5rem">
            @if ($st)<input type="hidden" name="status" value="{{ $st }}">@endif
            <div class="control has-icons-left">
                <input class="input is-small" name="q" value="{{ request('q') }}" placeholder="Buscar formulários…" style="width:220px">
                <span class="icon is-left is-small"><i class="fa-solid fa-magnifying-glass"></i></span>
            </div>
            <div class="select is-small">
                <select name="sort" onchange="this.form.submit()">
                    <option value="updated" @selected(request('sort', 'updated') === 'updated')>Editados recentemente</option>
                    <option value="responses" @selected(request('sort') === 'responses')>Mais respostas</option>
                    <option value="title" @selected(request('sort') === 'title')>Nome (A–Z)</option>
                </select>
            </div>
        </form>
    </div>

    @if ($forms->isEmpty())
        <div class="box empty">
            <div class="icon-big"><i class="fa-solid fa-file-circle-plus"></i></div>
            <h3>{{ request('q') || $st ? 'Nenhum formulário encontrado' : 'Nenhum formulário ainda' }}</h3>
            <p>{{ request('q') || $st ? 'Tente outro filtro ou termo de busca.' : 'Crie seu primeiro formulário a partir de um modelo pronto.' }}</p>
            @can('forms.create')<a href="{{ route('forms.create') }}" class="button is-primary">Criar formulário</a>@endcan
        </div>
    @else
        <div class="columns is-multiline is-variable is-3">
            @foreach ($forms as $form)
                @php $conv = $form->views_count ? round($form->responses_count / $form->views_count * 100, 1) : 0; @endphp
                <div class="column is-6-tablet is-4-desktop">
                    <div class="card form-card">
                        <div class="card-content">
                            <div class="is-flex is-justify-content-space-between is-align-items-flex-start mb-2">
                                <span class="tag {{ $form->statusColor() }} is-light"><span class="dot mr-2" style="background:currentColor"></span>{{ $form->statusLabel() }}</span>
                                <div class="dropdown is-right" x-data="{ o: false }" :class="{ 'is-active': o }" @click.outside="o = false">
                                    <button class="button is-small is-white" @click="o = !o" aria-label="Mais ações"><i class="fa-solid fa-ellipsis"></i></button>
                                    <div class="dropdown-menu"><div class="dropdown-content">
                                        @can('forms.edit', $form)<a href="{{ route('forms.edit', $form) }}" class="dropdown-item"><i class="fa-solid fa-pen-ruler mr-2"></i> Editar</a>@endcan
                                        <a href="{{ route('forms.share', $form) }}" class="dropdown-item"><i class="fa-solid fa-share-nodes mr-2"></i> Compartilhar</a>
                                        <a href="{{ $form->publicUrl($form->isPublished() ? [] : ['preview' => 1]) }}" target="_blank" class="dropdown-item"><i class="fa-solid fa-arrow-up-right-from-square mr-2"></i> Abrir</a>
                                        @can('forms.create')
                                            <form method="POST" action="{{ route('forms.duplicate', $form) }}">@csrf<button class="dropdown-item" style="border:0;background:none;width:100%;text-align:left;cursor:pointer"><i class="fa-regular fa-copy mr-2"></i> Duplicar</button></form>
                                        @endcan
                                        @can('forms.delete', $form)
                                            <hr class="dropdown-divider">
                                            <form method="POST" action="{{ route('forms.destroy', $form) }}" onsubmit="return confirm('Excluir “{{ addslashes($form->title) }}”? As respostas também serão removidas.')">@csrf @method('DELETE')
                                                <button class="dropdown-item has-text-danger" style="border:0;background:none;width:100%;text-align:left;cursor:pointer"><i class="fa-regular fa-trash-can mr-2"></i> Excluir</button>
                                            </form>
                                        @endcan
                                    </div></div>
                                </div>
                            </div>
                            <p class="title"><a href="{{ route('reports.show', $form) }}">{{ $form->title }}</a></p>
                            <p class="is-size-7 has-text-muted">
                                <i class="fa-solid {{ \App\Models\Form::LAYOUT_LABELS[$form->layout][2] ?? 'fa-list' }} mr-1"></i>{{ $form->layoutLabel() }}
                                · Editado {{ $form->updated_at->locale('pt_BR')->diffForHumans() }}
                                @if ($form->last_response_at) · Última resposta {{ \Carbon\Carbon::parse($form->last_response_at)->locale('pt_BR')->diffForHumans() }}@endif
                            </p>
                        </div>
                        <div class="stats">
                            <div><strong>{{ number_format($form->views_count, 0, ',', '.') }}</strong><span>Visitas</span></div>
                            <div><strong>{{ number_format($form->responses_count, 0, ',', '.') }}</strong><span>Respostas</span></div>
                            <div><strong>{{ number_format($conv, 1, ',', '.') }}%</strong><span>Conversão</span></div>
                        </div>
                        <footer class="card-footer">
                            <a href="{{ route('reports.show', $form) }}" class="card-footer-item"><i class="fa-solid fa-chart-line mr-2"></i> Relatórios</a>
                            @can('responses.view', $form)
                                <a href="{{ route('submissions.index', $form) }}" class="card-footer-item"><i class="fa-solid fa-inbox mr-2"></i> Respostas</a>
                            @endcan
                        </footer>
                    </div>
                </div>
            @endforeach
        </div>
        {{ $forms->links() }}
    @endif
@endsection
