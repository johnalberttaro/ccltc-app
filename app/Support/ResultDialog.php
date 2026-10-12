<?php

namespace App\Support;

use App\Models\TournamentMatch;

// Data the "Record result" and "Schedule" dialogs need for one match.
class ResultDialog
{
    public static function payload(TournamentMatch $m): array
    {
        $rows = $m->sets->map(fn ($s) => $s->is_match_tiebreak
            ? ['p1' => $s->participant1_tiebreak, 'p2' => $s->participant2_tiebreak, 'tb' => null]
            : [
                'p1' => $s->participant1_games,
                'p2' => $s->participant2_games,
                'tb' => $s->participant1_tiebreak !== null ? min($s->participant1_tiebreak, $s->participant2_tiebreak) : null,
            ])->values()->all();

        return [
            'id'        => $m->id,
            'code'      => $m->code,
            'round'     => $m->roundName(),
            'p1'        => $m->participant1?->name,
            'p2'        => $m->participant2?->name,
            'sets'      => $rows,
            'walkover'  => $m->is_walkover && $m->winner_id ? ($m->wonBy(1) ? 1 : 2) : null,
            'decided'   => (bool) $m->winner_id,
            'action'    => route('admin.tournaments.matches.result', [$m->tournament_id, $m->id]),
            'clear'     => route('admin.tournaments.matches.clear', [$m->tournament_id, $m->id]),
            'schedule'  => route('admin.tournaments.matches.schedule', [$m->tournament_id, $m->id]),
            'when'      => $m->scheduled_at?->format('Y-m-d\TH:i'),
            'court'     => $m->court,
            'status'    => $m->status,
            'canCancel' => $m->stage === 'group',
        ];
    }
}