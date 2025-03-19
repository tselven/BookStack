@extends('layouts.tri')
@push('head')
<script src="{{ asset('modal.js') }}" nonce="{{ $cspNonce }}"></script>
@endpush

@section('container-attrs')
component="entity-search"
option:entity-search:entity-id="{{ $book->id }}"
option:entity-search:entity-type="book"
@stop

@push('social-meta')
<meta property="og:description" content="{{ Str::limit($book->description, 100, '...') }}">
@if($book->cover)
<meta property="og:image" content="{{ $book->getBookCover() }}">
@endif
@endpush

@include('entities.body-tag-classes', ['entity' => $book])

@section('body')
<!-- Modal -->
<!-- Modal Container -->
<div id="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.8); z-index: 99999; justify-content: center; align-items: center;">

    <div id="modal-box" style="margin-top:70px; background: white; padding: 20px; border-radius: 10px; width: 80vw; height: 85vh; box-shadow: 0px 0px 10px rgba(0, 0, 0, 1.0); position: relative;">

        <!-- Close Button -->
        <button id="closeModalBtn" style="position: absolute; top: 10px; right: 10px; background: red; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 5px;">Close</button>
        <!-- Flipbook Container -->
        <div class="entity-list book-contents" id="modal-cont">
            
        </div>

        <!-- Navigation Buttons -->
        <button id="prevPageBtn" style="position: absolute; left: 10px; bottom: 50%; transform: translateY(50%); background: gray; color: white; border: none; padding: 10px; cursor: pointer; border-radius: 5px;">◀</button>
        <button id="nextPageBtn" style="position: absolute; right: 10px; bottom: 50%; transform: translateY(50%); background: gray; color: white; border: none; padding: 10px; cursor: pointer; border-radius: 5px;">▶</button>

    </div>
</div>


<div class="mb-s print-hidden">
    @include('entities.breadcrumbs', ['crumbs' => [
    $book,
    ]])

</div>


<main class="content-wrap card">
    <h1 class="break-text">{{$book->name}}</h1>
    <div refs="entity-search@contentView" class="book-content">
        <div class="text-muted break-text">{!! $book->descriptionHtml() !!}</div>
        @if(count($bookChildren) > 0)
        <div class="entity-list book-contents">
            @foreach($bookChildren as $childElement)
            @if($childElement->isA('chapter'))
            @include('chapters.parts.list-item', ['chapter' => $childElement])
            @else
            @include('pages.parts.list-item', ['page' => $childElement])
            @endif
            @endforeach
        </div>
        @else
        <div class="mt-xl">
            <hr>
            <p class="text-muted italic mb-m mt-xl">{{ trans('entities.books_empty_contents') }}</p>

            <div class="icon-list block inline">

                @if(userCan('page-create', $book))
                <a href="{{ $book->getUrl('/create-page') }}" class="icon-list-item text-page">
                    <span class="icon">@icon('page')</span>
                    <span>{{ trans('entities.books_empty_create_page') }}</span>
                </a>
                @endif
                @if(userCan('chapter-create', $book))
                <a href="{{ $book->getUrl('/create-chapter') }}" class="icon-list-item text-chapter">
                    <span class="icon">@icon('chapter')</span>
                    <span>{{ trans('entities.books_empty_add_chapter') }}</span>
                </a>
                @endif
            </div>

        </div>
        @endif
    </div>

    @include('entities.search-results')
</main>

@stop

@section('right')
<div class="mb-xl">
    <h5>{{ trans('common.details') }}</h5>
    <div class="blended-links">
        @include('entities.meta', ['entity' => $book, 'watchOptions' => $watchOptions])
        @if($book->hasPermissions())
        <div class="active-restriction">
            @if(userCan('restrictions-manage', $book))
            <a href="{{ $book->getUrl('/permissions') }}" class="entity-meta-item">
                @icon('lock')
                <div>{{ trans('entities.books_permissions_active') }}</div>
            </a>
            @else
            <div class="entity-meta-item">
                @icon('lock')
                <div>{{ trans('entities.books_permissions_active') }}</div>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>

<div class="actions mb-xl">
    <h5>{{ trans('common.actions') }}</h5>
    <div class="icon-list text-link">

        @if(userCan('page-create', $book))
        <a href="{{ $book->getUrl('/create-page') }}" data-shortcut="new" class="icon-list-item">
            <span>@icon('add')</span>
            <span>{{ trans('entities.pages_new') }}</span>
        </a>
        @endif
        @if(userCan('chapter-create', $book))
        <a href="{{ $book->getUrl('/create-chapter') }}" data-shortcut="new" class="icon-list-item">
            <span>@icon('add')</span>
            <span>{{ trans('entities.chapters_new') }}</span>
        </a>
        @endif

        <hr class="primary-background">
        <a href="#" id="openModalBtn" data-shortcut="read" api-url="{{ url('/api/books') }}" book-id="{{ $book->id }}" class="icon-list-item">
            <span>@icon('book')</span>
            <span>{{ trans('common.read') }}</span>
        </a>
        @if(userCan('book-update', $book))
        

        <a href="{{ $book->getUrl('/edit') }}" data-shortcut="edit" class="icon-list-item">
            <span>@icon('edit')</span>
            <span>{{ trans('common.edit') }}</span>
        </a>
        <a href="{{ $book->getUrl('/sort') }}" data-shortcut="sort" class="icon-list-item">
            <span>@icon('sort')</span>
            <span>{{ trans('common.sort') }}</span>
        </a>
        @endif
        @if(userCan('book-create-all'))
        <a href="{{ $book->getUrl('/copy') }}" data-shortcut="copy" class="icon-list-item">
            <span>@icon('copy')</span>
            <span>{{ trans('common.copy') }}</span>
        </a>
        @endif
        @if(userCan('restrictions-manage', $book))
        <a href="{{ $book->getUrl('/permissions') }}" data-shortcut="permissions" class="icon-list-item">
            <span>@icon('lock')</span>
            <span>{{ trans('entities.permissions') }}</span>
        </a>
        @endif
        @if(userCan('book-delete', $book))
        <a href="{{ $book->getUrl('/delete') }}" data-shortcut="delete" class="icon-list-item">
            <span>@icon('delete')</span>
            <span>{{ trans('common.delete') }}</span>
        </a>
        @endif

        <hr class="primary-background">

        @if($watchOptions->canWatch() && !$watchOptions->isWatching())
        @include('entities.watch-action', ['entity' => $book])
        @endif
        @if(!user()->isGuest())
        @include('entities.favourite-action', ['entity' => $book])
        @endif
        @if(userCan('content-export'))
        @include('entities.export-menu', ['entity' => $book])
        @endif
    </div>
</div>

@stop

@section('left')

@include('entities.search-form', ['label' => trans('entities.books_search_this')])

@if($book->tags->count() > 0)
<div class="mb-xl">
    @include('entities.tag-list', ['entity' => $book])
</div>
@endif

@if(count($bookParentShelves) > 0)
<div class="actions mb-xl">
    <h5>{{ trans('entities.shelves') }}</h5>
    @include('entities.list', ['entities' => $bookParentShelves, 'style' => 'compact'])
</div>
@endif

@if(count($activity) > 0)
<div id="recent-activity" class="mb-xl">
    <h5>{{ trans('entities.recent_activity') }}</h5>
    @include('common.activity-list', ['activity' => $activity])
</div>
@endif
@stop
