{{--
    A small pagination control. Written by hand because Laravel's bundled
    paginator views assume Tailwind or Bootstrap, and this app ships neither —
    the stylesheet is plain CSS with no build step.
--}}
@if ($paginator->hasPages())
    <div class="pager">
        <span>
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            of {{ $paginator->total() }}
        </span>

        <div class="pager__links">
            @if ($paginator->onFirstPage())
                <span class="is-disabled">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach ($paginator->getUrlRange(
                max(1, $paginator->currentPage() - 2),
                min($paginator->lastPage(), $paginator->currentPage() + 2)
            ) as $page => $url)
                @if ($page === $paginator->currentPage())
                    <span class="is-current">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="is-disabled">Next</span>
            @endif
        </div>
    </div>
@endif
