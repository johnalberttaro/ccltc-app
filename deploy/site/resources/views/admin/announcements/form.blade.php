@extends('layouts.admin')

@section('title', $announcement->exists ? 'Edit Announcement' : 'Post Announcement')

@section('content')
    <a href="{{ route('admin.announcements.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
        <x-icon name="arrow-left" class="w-4 h-4" /> Back to announcements
    </a>
    <h1 class="text-2xl font-bold mt-2 mb-4">{{ $announcement->exists ? 'Edit Announcement' : 'Post Announcement' }}</h1>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
          class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-5">
        @csrf
        @if ($announcement->exists) @method('PUT') @endif

        @include('admin.partials.errors')

        <div>
            <label class="block text-sm font-medium mb-1">Title</label>
            <input type="text" name="title" value="{{ old('title', $announcement->title) }}" required placeholder="e.g. New rule: No-ad scoring starts Monday"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Content</label>
            <textarea name="body" rows="8" required placeholder="Write the full announcement here. Line breaks are kept."
                      class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('body', $announcement->body) }}</textarea>
        </div>

        {{-- When and where the meeting / event happens (shown big on the public page) --}}
        <div class="grid gap-4 md:grid-cols-2 rounded-xl border border-gray-200 p-4">
            <div>
                <label for="event_at" class="block text-sm font-medium mb-1">Meeting / event date &amp; time <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="datetime-local" name="event_at" id="event_at"
                       value="{{ old('event_at', $announcement->event_at?->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">When the meeting or event happens. Leave empty for a general announcement.</p>
            </div>
            <div>
                <label for="event_place" class="block text-sm font-medium mb-1">Place <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="event_place" id="event_place" maxlength="150"
                       value="{{ old('event_place', $announcement->event_place) }}" placeholder="e.g. Clubhouse"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
        </div>

        @include('admin.partials.image-field', ['model' => $announcement, 'label' => 'Image / poster'])

        <div class="grid gap-4 md:grid-cols-3 md:items-end">
            <div>
                <label class="block text-sm font-medium mb-1">Show on the public page from <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="datetime-local" name="published_at" id="published_at"
                       value="{{ old('published_at', $announcement->published_at?->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Leave empty to show it <strong>right away</strong>. This is when the post appears, not the meeting date (use the box above for that).</p>
                <p id="schedule-warning" class="hidden text-xs mt-1 rounded bg-violet-50 text-violet-800 px-2 py-1">
                    Future date: this announcement stays hidden until then.
                </p>
            </div>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                <input type="hidden" name="is_published" value="0">
                <input type="checkbox" name="is_published" value="1" class="mt-1 rounded border-gray-300"
                       @checked(old('is_published', $announcement->is_published))>
                <span>
                    <span class="block text-sm font-medium">Published</span>
                    <span class="block text-xs text-gray-500">Visible on the public Announcements page.</span>
                </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                <input type="hidden" name="is_pinned" value="0">
                <input type="checkbox" name="is_pinned" value="1" class="mt-1 rounded border-gray-300"
                       @checked(old('is_pinned', $announcement->is_pinned))>
                <span>
                    <span class="block text-sm font-medium">Important / pinned</span>
                    <span class="block text-xs text-gray-500">Stays at the top and shows on the scores page.</span>
                </span>
            </label>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t">
            <a href="{{ route('admin.announcements.index') }}" class="rounded-lg px-4 py-2.5 text-center text-gray-700 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="rounded-lg bg-green-700 text-white font-semibold px-5 py-2.5 hover:bg-green-800">
                {{ $announcement->exists ? 'Save Changes' : 'Post Announcement' }}
            </button>
        </div>
    </form>
    <script>
        (function () {
            var input = document.getElementById('published_at');
            var warning = document.getElementById('schedule-warning');
            function check() {
                var future = input.value && new Date(input.value) > new Date();
                warning.classList.toggle('hidden', !future);
            }
            input.addEventListener('input', check);
            check();
        })();
    </script>
@endsection