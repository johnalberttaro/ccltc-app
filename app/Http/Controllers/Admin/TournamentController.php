<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    use HandlesImageUpload;

    // Settings that can't change once groups / the bracket have been drawn
    private const DRAW_FIELDS = ['format', 'group_count', 'group_size', 'advance_per_group', 'knockout_format', 'grand_final_reset'];

    // Settings that can't change once a result has been recorded
    private const RULE_FIELDS = ['best_of', 'games_per_set', 'final_set'];

    public function index(Request $request)
    {
        $tournaments = Tournament::query()
            ->withCount('participants')
            ->search($request->input('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->displayOrder()
            ->paginate(10)
            ->withQueryString();

        return view('admin.tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('admin.tournaments.form', ['tournament' => new Tournament([
            'status'            => 'draft',
            'match_type'        => 'doubles',
            'format'            => 'group_knockout',
            'group_count'       => 4,
            'group_size'        => 4,
            'advance_per_group' => 2,
            'knockout_format'   => 'single',
            'grand_final_reset' => true,
            'best_of'           => 1,
            'games_per_set'     => 6,
            'final_set'         => 'full',
            'points_win'        => 3,
            'points_loss'       => 0,
            'standings_method'  => 'difference',
            'use_head_to_head'  => true,
        ])]);
    }

    public function store(Request $request)
    {
        $tournament = new Tournament($this->validated($request));
        $this->applyImage($request, $tournament, 'tournaments');
        $tournament->save();

        return redirect()->route('admin.tournaments.manage', [$tournament, 'participants'])
            ->with('success', 'Tournament created. Next, add the participants.');
    }

    public function edit(Tournament $tournament)
    {
        return view('admin.tournaments.form', [
            'tournament' => $tournament,
            'drawLocked' => $tournament->drawStarted(),
            'rulesLocked' => $tournament->hasResults(),
        ]);
    }

    public function update(Request $request, Tournament $tournament)
    {
        $data = $this->validated($request, $tournament);

        // Ignore locked settings even if someone edits the page
        if ($tournament->drawStarted()) {
            $data = Arr::except($data, self::DRAW_FIELDS);
        }
        if ($tournament->hasResults()) {
            $data = Arr::except($data, self::RULE_FIELDS);
        }

        $tournament->fill($data);
        $this->applyImage($request, $tournament, 'tournaments');
        $tournament->save();

        return redirect()->route('admin.tournaments.manage', [$tournament, 'overview'])
            ->with('success', 'Tournament "' . $tournament->name . '" was updated.');
    }

    public function destroy(Tournament $tournament)
    {
        $this->deleteImage($tournament);
        $tournament->delete();

        return redirect()->route('admin.tournaments.index')->with('success', 'Tournament deleted.');
    }

    private function validated(Request $request, ?Tournament $existing = null): array
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:150',
            'category'              => 'nullable|string|max:100',
            'match_type'            => ['required', Rule::in(array_keys(Tournament::TYPES))],
            'format'                => ['required', Rule::in(array_keys(Tournament::FORMATS))],
            'starts_at'             => 'required|date',
            'ends_at'               => 'nullable|date|after_or_equal:starts_at',
            'venue'                 => 'nullable|string|max:150',
            'court'                 => 'nullable|string|max:150',
            'registration_deadline' => 'nullable|date',
            'max_participants'      => 'nullable|integer|min:2|max:256',
            'description'           => 'nullable|string|max:5000',
            'registration_info'     => 'nullable|string|max:3000',
            'registration_link'     => 'nullable|url|max:255',
            'status'                => ['required', Rule::in(array_keys(Tournament::STATUSES))],
            'image'                 => $this->imageRule(),

            'group_count'           => 'required_if:format,group_knockout|nullable|integer|min:1|max:16',
            'group_size'            => 'required_if:format,group_knockout|nullable|integer|min:2|max:16',
            'advance_per_group'     => 'required_if:format,group_knockout|nullable|integer|min:1|max:8',
            'knockout_format'       => ['nullable', Rule::in(array_keys(Tournament::KNOCKOUT_FORMATS))],

            'best_of'               => ['required', Rule::in(array_keys(Tournament::SCORE_FORMATS))],
            'games_per_set'         => ['required', Rule::in(array_keys(Tournament::GAMES_PER_SET))],
            'final_set'             => ['nullable', Rule::in(array_keys(Tournament::FINAL_SET))],
            'points_win'            => 'required|integer|min:0|max:20',
            'points_loss'           => 'required|integer|min:0|max:20',
            'standings_method'      => ['required', Rule::in(array_keys(Tournament::STANDINGS_METHODS))],
        ], [
            'ends_at.after_or_equal'  => 'The end date must be after the start date.',
            'image.max'               => 'The image must be 5 MB or smaller.',
            'group_count.required_if' => 'Enter the number of groups.',
            'group_size.required_if'  => 'Enter the players per group.',
            'advance_per_group.required_if' => 'Enter how many advance from each group.',
        ]);

        $data['grand_final_reset'] = $request->boolean('grand_final_reset');
        $data['use_head_to_head'] = $request->boolean('use_head_to_head');
        $data['knockout_format'] = $data['knockout_format'] ?? 'single';

        // A 10-point match tiebreak only makes sense as a deciding set
        if ((int) $data['best_of'] === 1 || empty($data['final_set'])) {
            $data['final_set'] = 'full';
        }

        if ($data['format'] === 'group_knockout') {
            if ((int) $data['advance_per_group'] > (int) $data['group_size']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'advance_per_group' => 'More players cannot advance than there are in a group.',
                ]);
            }
            if ((int) $data['group_count'] * (int) $data['advance_per_group'] < 2) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'advance_per_group' => 'At least 2 players in total must advance to the knockout stage.',
                ]);
            }
        } else {
            // Keep the existing group numbers when another format is chosen
            foreach (['group_count', 'group_size', 'advance_per_group'] as $field) {
                if (empty($data[$field])) {
                    $data[$field] = $existing?->{$field} ?? ['group_count' => 4, 'group_size' => 4, 'advance_per_group' => 2][$field];
                }
            }
        }

        if ($existing && ! empty($data['max_participants'])) {
            $count = $existing->participants()->count();
            if ($count > (int) $data['max_participants']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'max_participants' => "There are already {$count} participants. Remove some first or raise the limit.",
                ]);
            }
        }

        return Arr::except($data, ['image']);
    }
}