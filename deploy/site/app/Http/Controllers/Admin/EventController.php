<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\TennisEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request)
    {
        $events = TennisEvent::query()
            ->search($request->input('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->displayOrder()
            ->paginate(10)
            ->withQueryString();

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.events.form', ['event' => new TennisEvent(['status' => 'upcoming'])]);
    }

    public function store(Request $request)
    {
        $event = new TennisEvent($this->validated($request));
        $this->applyImage($request, $event, 'events');
        $event->save();

        return redirect()->route('admin.events.index')->with('success', 'Event "' . $event->title . '" was added.');
    }

    public function edit(TennisEvent $event)
    {
        return view('admin.events.form', compact('event'));
    }

    public function update(Request $request, TennisEvent $event)
    {
        $event->fill($this->validated($request));
        $this->applyImage($request, $event, 'events');
        $event->save();

        return redirect()->route('admin.events.index')->with('success', 'Event "' . $event->title . '" was updated.');
    }

    public function destroy(TennisEvent $event)
    {
        $this->deleteImage($event);
        $event->delete();

        return back()->with('success', 'Event deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'             => 'required|string|max:150',
            'description'       => 'nullable|string|max:5000',
            'starts_at'         => 'required|date',
            'ends_at'           => 'nullable|date|after_or_equal:starts_at',
            'location'          => 'nullable|string|max:150',
            'registration_info' => 'nullable|string|max:3000',
            'registration_link' => 'nullable|url|max:255',
            'status'            => ['required', Rule::in(array_keys(TennisEvent::STATUSES))],
            'image'             => $this->imageRule(),
        ], [
            'ends_at.after_or_equal' => 'The end time must be after the start time.',
            'image.max'              => 'The image must be 5 MB or smaller.',
        ]);

        return Arr::except($data, ['image']);
    }
}