@if($records->hasPages())
<nav class="portal-pagination" aria-label="Pagination">
    <span>Showing {{ $records->firstItem() }}&ndash;{{ $records->lastItem() }} of {{ $records->total() }}</span>
    <ul class="pagination">
        <li class="{{ $records->onFirstPage() ? 'disabled' : '' }}">@if($records->onFirstPage())<span>Previous</span>@else<a href="{{ $records->previousPageUrl() }}" rel="prev">Previous</a>@endif</li>
        <li class="active"><span>{{ $records->currentPage() }} / {{ $records->lastPage() }}</span></li>
        <li class="{{ !$records->hasMorePages() ? 'disabled' : '' }}">@if($records->hasMorePages())<a href="{{ $records->nextPageUrl() }}" rel="next">Next</a>@else<span>Next</span>@endif</li>
    </ul>
</nav>
@endif
