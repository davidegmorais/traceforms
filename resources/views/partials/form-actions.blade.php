{{-- Ações do topo para páginas de um formulário --}}
<span class="tag {{ $form->statusColor() }} is-light is-medium"><span class="dot mr-2" style="background: currentColor"></span>{{ $form->statusLabel() }}</span>
<a href="{{ $form->publicUrl($form->isPublished() ? [] : ['preview' => 1]) }}" target="_blank" class="button is-small"><i class="fa-solid fa-arrow-up-right-from-square mr-2"></i> {{ $form->isPublished() ? 'Abrir' : 'Pré-visualizar' }}</a>
@can('forms.publish', $form)
    <div class="dropdown is-right" x-data="{ o: false }" :class="{ 'is-active': o }" @click.outside="o = false">
        <div class="dropdown-trigger">
            @if ($form->isPublished())
                <button class="button is-small" @click="o = !o" title="Encerrar ou despublicar"><i class="fa-solid fa-ellipsis"></i></button>
            @else
                <form method="POST" action="{{ route('forms.status', $form) }}" class="is-inline">@csrf
                    <input type="hidden" name="status" value="published">
                    <button class="button is-small is-primary"><i class="fa-solid fa-rocket mr-2"></i> Publicar</button>
                </form>
            @endif
        </div>
        @if ($form->isPublished())
            <div class="dropdown-menu"><div class="dropdown-content">
                <form method="POST" action="{{ route('forms.status', $form) }}">@csrf
                    <button name="status" value="closed" class="dropdown-item" style="border:0;background:none;cursor:pointer;width:100%;text-align:left"><i class="fa-solid fa-lock mr-2"></i> Encerrar respostas</button>
                    <button name="status" value="draft" class="dropdown-item" style="border:0;background:none;cursor:pointer;width:100%;text-align:left"><i class="fa-solid fa-eye-slash mr-2"></i> Voltar para rascunho</button>
                </form>
            </div></div>
        @endif
    </div>
@endcan
