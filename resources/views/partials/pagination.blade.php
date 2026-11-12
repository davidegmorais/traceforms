@if ($paginator->hasPages())
    <nav class="pagination is-centered is-small mt-5" role="navigation" aria-label="Paginação">
        <a class="pagination-previous" @if ($paginator->onFirstPage()) disabled @else href="{{ $paginator->previousPageUrl() }}" @endif><i class="fa-solid fa-chevron-left mr-1"></i> Anterior</a>
        <a class="pagination-next" @if (! $paginator->hasMorePages()) disabled @else href="{{ $paginator->nextPageUrl() }}" @endif>Próxima <i class="fa-solid fa-chevron-right ml-1"></i></a>
        <ul class="pagination-list">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pagination-ellipsis">&hellip;</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li><a class="pagination-link {{ $page == $paginator->currentPage() ? 'is-current' : '' }}" href="{{ $url }}">{{ $page }}</a></li>
                    @endforeach
                @endif
            @endforeach
        </ul>
    </nav>
@endif
