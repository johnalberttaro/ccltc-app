<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AnnouncementController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request)
    {
        $announcements = Announcement::query()
            ->search($request->input('q'))
            ->when($request->input('filter') === 'published', fn ($q) => $q->where('is_published', true))
            ->when($request->input('filter') === 'unpublished', fn ($q) => $q->where('is_published', false))
            ->when($request->input('filter') === 'pinned', fn ($q) => $q->where('is_pinned', true))
            ->displayOrder()
            ->paginate(10)
            ->withQueryString();

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.form', [
            'announcement' => new Announcement(['is_published' => true, 'is_pinned' => false]),
        ]);
    }

    public function store(Request $request)
    {
        $announcement = new Announcement($this->validated($request));
        $this->applyImage($request, $announcement, 'announcements');
        $announcement->save();

        return redirect()->route('admin.announcements.index')
            ->with('success', $announcement->is_published ? 'Announcement posted.' : 'Announcement saved as unpublished.');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $announcement->fill($this->validated($request, $announcement));
        $this->applyImage($request, $announcement, 'announcements');
        $announcement->save();

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->deleteImage($announcement);
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    // Quick publish / unpublish from the list (a scheduled one is published right now)
    public function togglePublish(Announcement $announcement)
    {
        if ($announcement->isScheduled()) {
            $announcement->published_at = now();
            $announcement->save();

            return back()->with('success', 'Announcement is now live on the public page.');
        }

        $announcement->is_published = ! $announcement->is_published;
        if ($announcement->is_published && (! $announcement->published_at || $announcement->published_at->isFuture())) {
            $announcement->published_at = now();
        }
        $announcement->save();

        return back()->with('success', $announcement->is_published ? 'Announcement published.' : 'Announcement unpublished.');
    }

    // Quick pin / unpin from the list
    public function togglePin(Announcement $announcement)
    {
        $announcement->is_pinned = ! $announcement->is_pinned;
        $announcement->save();

        return back()->with('success', $announcement->is_pinned ? 'Announcement pinned to the top.' : 'Announcement unpinned.');
    }

    private function validated(Request $request, ?Announcement $existing = null): array
    {
        $data = $request->validate([
            'title'        => 'required|string|max:150',
            'body'         => 'required|string|max:10000',
            'event_at'     => 'nullable|date',
            'event_place'  => 'nullable|string|max:150',
            'published_at' => 'nullable|date',
            'image'        => $this->imageRule(),
        ], [
            'image.max' => 'The image must be 5 MB or smaller.',
        ]);

        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['is_published'] = $request->boolean('is_published');

        // Publishing with no date = publish right now
        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = $existing?->published_at ?? now();
        }

        return Arr::except($data, ['image']);
    }
}