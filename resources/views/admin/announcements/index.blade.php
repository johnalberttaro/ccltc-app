@extends('layouts.admin')

@section('title', 'Announcements')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center"><x-icon name="megaphone" /></span>
            <div>
                <h1 class="text-2xl font-bold">Announcements</h1>
                <p class="text-sm text-gray-500">Rules, schedule changes, reminders and notices.</p>
            </div>
        </div>
        <a href="{{ route('admin.announcements.create') }}"
           class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">
            <x-icon name="plus" class="w-4 h-4" /> Post Announcement
        </a>
    </div>

    {{-- Search & filter --}}
    <form method="GET" class="bg-white rounded-xl shadow p-3 mb-5 flex flex-col sm:flex-row gap-2">
        <div class="relative flex-1">
            <x-icon name="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search title or content"
                   class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2">
        </div>
        <select name="filter" class="rounded-lg border border-gray-300 px-3 py-2" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="published" @selected(request('filter') === 'published')>Published</option>
            <option value="unpublished" @selected(request('filter') === 'unpublished')>Unpublished</option>
            <option value="pinned" @selected(request('filter') === 'pinned')>Pinned</option>
        </select>
        <button class="rounded-lg bg-gray-900 text-white px-4 py-2 hover:bg-gray-800">Search</button>
        @if (request()->hasAny(['q', 'filter']))
            <a href="{{ route('admin.announcements.index') }}" class="rounded-lg px-4 py-2 text-center text-gray-600 hover:bg-gray-100">Clear</a>
        @endif
    </form>

    @if ($announcements->isEmpty())
        <div class="bg-white rounded-xl shadow p-10 text-center">
            <x-icon name="megaphone" class="w-10 h-10 mx-auto text-gray-300" />
            <p class="mt-2 font-semibold">No announcements found.</p>
            <a href="{{ route('admin.announcements.create') }}" class="inline-block mt-2 text-green-700 font-medium hover:underline">Post your first announcement &rarr;</a>
        </div>
    @else
        <div class="bg-white rounded-xl shadow divide-y">
            @foreach ($announcements as $announcement)
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center {{ $announcement->is_published ? '' : 'bg-gray-50' }}">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ $announcement->title }}</h2>
                            @if ($announcement->is_pinned)
                                <x-status-badge status="pinned" />
                            @endif
                            @if ($announcement->isScheduled())
                                <x-status-badge status="scheduled" />
                            @elseif ($announcement->is_published)
                                <x-status-badge status="published" />
                            @else
                                <x-status-badge status="unpublished" />
                            @endif
                        </div>
                        @if ($announcement->event_at)
                            <p class="text-sm font-medium text-green-700 mt-1 inline-flex items-center gap-1">
                                <x-icon name="calendar" class="w-4 h-4" /> {{ $announcement->eventLabel(true) }}@if ($announcement->event_place) &middot; {{ $announcement->event_place }}@endif
                            </p>
                        @endif
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $announcement->body }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            @if ($announcement->published_at)
                                @if ($announcement->isScheduled())
                                    <span class="text-violet-700 font-medium">Hidden from the public until {{ $announcement->published_at->format('M j, Y g:i A') }}</span>
                                @else
                                    Published {{ $announcement->published_at->format('M j, Y g:i A') }}
                                @endif
                            @else
                                Not published yet
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1 md:justify-end">
                        <form method="POST" action="{{ route('admin.announcements.publish', $announcement) }}">
                            @csrf
                            @method('PATCH')
                            @if ($announcement->isScheduled())
                                <button class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm hover:bg-gray-100 text-green-700 font-medium">
                                    <x-icon name="eye" class="w-4 h-4" /><span>Publish now</span>
                                </button>
                            @else
                                <button class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm hover:bg-gray-100 {{ $announcement->is_published ? 'text-gray-700' : 'text-green-700 font-medium' }}">
                                    <x-icon :name="$announcement->is_published ? 'eye-off' : 'eye'" class="w-4 h-4" />
                                    <span>{{ $announcement->is_published ? 'Unpublish' : 'Publish' }}</span>
                                </button>
                            @endif
                        </form>
                        <form method="POST" action="{{ route('admin.announcements.pin', $announcement) }}">
                            @csrf
                            @method('PATCH')
                            <button class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm hover:bg-gray-100 {{ $announcement->is_pinned ? 'text-yellow-700' : 'text-gray-700' }}">
                                <x-icon name="star" class="w-4 h-4" />
                                <span>{{ $announcement->is_pinned ? 'Unpin' : 'Pin' }}</span>
                            </button>
                        </form>
                        <a href="{{ route('admin.announcements.edit', $announcement) }}"
                           class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                            <x-icon name="pencil" class="w-4 h-4" /><span>Edit</span>
                        </a>
                        @include('admin.partials.delete-button', ['action' => route('admin.announcements.destroy', $announcement), 'confirm' => 'Delete this announcement? This cannot be undone.'])
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $announcements->links() }}</div>
    @endif
@endsection