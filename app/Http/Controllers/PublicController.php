<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\TennisMatch;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    // Public page: games, scores, winners and losers for a date (default: today)
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $date = Carbon::parse($request->input('date', today()->toDateString()));

        $matches = TennisMatch::with(['players', 'sets'])
            ->whereDate('match_date', $date)
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        // Everyone who played on this date, with their wins and losses
        $playersToday = [];
        foreach ($matches as $match) {
            foreach ($match->players as $player) {
                $id = $player->id;
                $playersToday[$id] ??= ['name' => $player->full_name, 'played' => 0, 'wins' => 0, 'losses' => 0];
                $playersToday[$id]['played']++;

                if ($match->winner_side) {
                    if ((int) $player->pivot->side === (int) $match->winner_side) {
                        $playersToday[$id]['wins']++;
                    } else {
                        $playersToday[$id]['losses']++;
                    }
                }
            }
        }

        $playersToday = collect($playersToday)
            ->sortBy([['wins', 'desc'], ['losses', 'asc'], ['name', 'asc']])
            ->values();

        // Pinned announcements shown at the top of the scores page
        $pinned = Announcement::published()->where('is_pinned', true)->displayOrder()->take(3)->get();

        return view('public.index', compact('date', 'matches', 'playersToday', 'pinned'));
    }

    // Public match details
    public function show(TennisMatch $match)
    {
        $match->load(['players', 'sets']);

        return view('public.show', compact('match'));
    }
}