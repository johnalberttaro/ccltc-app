@extends('layouts.admin')

@section('title', $event->exists ? 'Edit Event' : 'Add Event')

@section('content')
    <a href="{{ route('admin.events.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
        <x-icon name="arrow-left" class="w-4 h-4" /> Back to events
    </a>
    <h1 class="text-2xl font-bold mt-2 mb-4">{{ $event->exists ? 'Edit Event' : 'Add Event' }}</h1>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}"
          class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-5">
        @csrf
        @if ($event->exists) @method('PUT') @endif

        @include('admin.partials.errors')

        <div>
            <label class="block text-sm font-medium mb-1">Event title</label>
            <input type="text" name="title" value="{{ old('title', $event->title) }}" required placeholder="e.g. Saturday Social Doubles"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Description</label>
            <textarea name="description" rows="4" placeholder="What is this event about?"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('description', $event->description) }}</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium mb-1">Starts</label>
                <input type="datetime-local" name="starts_at" required
                       value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Ends <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="datetime-local" name="ends_at"
                       value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    @foreach (\App\Models\TennisEvent::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $event->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Location / venue</label>
            <input type="text" name="location" value="{{ old('location', $event->location) }}" placeholder="e.g. Court 1, City Sports Complex"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>

        @include('admin.partials.image-field', ['model' => $event, 'label' => 'Event image / poster'])

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium mb-1">Registration details <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea name="registration_info" rows="3" placeholder="Fee, who can join, how to sign up..."
                          class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('registration_info', $event->registration_info) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Registration link <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="url" name="registration_link" value="{{ old('registration_link', $event->registration_link) }}" placeholder="https://forms.gle/..."
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">A Google Form or any sign-up page.</p>
            </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t">
            <a href="{{ route('admin.events.index') }}" class="rounded-lg px-4 py-2.5 text-center text-gray-700 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="rounded-lg bg-green-700 text-white font-semibold px-5 py-2.5 hover:bg-green-800">
                {{ $event->exists ? 'Save Changes' : 'Add Event' }}
            </button>
        </div>
    </form>
@endsection