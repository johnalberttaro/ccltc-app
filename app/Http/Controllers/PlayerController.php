<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Club members, typed by the admin.
// Names typed in Record Match that are not members are kept separately (is_member = false).
class PlayerController extends Controller
{
    // Admin: list members
    public function index()
    {
        $players = Player::where('is_member', true)->withCount('matches')
            ->orderBy('first_name')->orderBy('last_name')->get();

        return view('admin.players.index', compact('players'));
    }

    // Admin: add members (one full name per line)
    public function store(Request $request)
    {
        $request->validate([
            'names' => 'required|string|max:5000',
        ], [
            'names.required' => 'Type the full name of the member.',
        ]);

        $names = collect(preg_split('/\r\n|\r|\n/', $request->input('names')))
            ->map(fn ($line) => self::cleanName($line))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();

        $everyone = Player::all();
        $added = [];
        $skipped = [];

        foreach ($names as $name) {
            if (mb_strlen($name) > 100) {
                throw ValidationException::withMessages(['names' => "This name is too long: $name"]);
            }

            $existing = self::findByName($name, $everyone);
            if ($existing && $existing->is_member) {
                $skipped[] = $name;
                continue;
            }

            if ($existing) {
                // Name was already used in a match as a non-member: make them a member (keeps their games)
                $existing->is_member = true;
                $existing->save();
            } else {
                $player = new Player(self::splitName($name));
                $player->is_member = true;
                $player->save();
                $everyone->push($player);
            }
            $added[] = $name;
        }

        if (! $added) {
            return back()->withInput()->with('error', 'Already in Members: ' . implode(', ', $skipped));
        }

        $message = count($added) === 1 ? "Member added: {$added[0]}" : count($added) . ' members added.';
        if ($skipped) {
            $message .= ' (Already in Members, skipped: ' . implode(', ', $skipped) . ')';
        }

        return redirect()->route('admin.players.index')->with('success', $message);
    }

    // Admin: fix a member's name (their past games keep the new name)
    public function update(Request $request, Player $player)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $name = self::cleanName($request->input('name'));

        $taken = Player::where('id', '!=', $player->id)->get()
            ->contains(fn ($p) => mb_strtolower(self::cleanName($p->full_name)) === mb_strtolower($name));
        if ($taken) {
            return back()->with('error', "This name is already used: $name");
        }

        $player->update(self::splitName($name));

        return redirect()->route('admin.players.index')->with('success', "Name updated: $name");
    }

    // Admin: remove someone from Members (they no longer show anywhere in Members).
    // With games: their games stay on the scores (the record is only hidden, not deleted).
    // Without games: the name is deleted.
    // Adding the same name again in Members brings them back with their games.
    public function remove(Player $player)
    {
        $name = $player->full_name;

        if ($player->matches()->exists()) {
            $player->is_member = false;
            $player->save();

            return redirect()->route('admin.players.index')
                             ->with('success', "$name was removed from Members.");
        }

        $player->delete();

        return redirect()->route('admin.players.index')->with('success', "$name was removed.");
    }

    // Admin: delete a member (only if they have no games)
    public function destroy(Player $player)
    {
        if ($player->matches()->exists()) {
            return back()->with('error', 'Cannot delete a member who has games recorded.');
        }

        $player->delete();

        return redirect()->route('admin.players.index')
                         ->with('success', $player->is_member ? 'Member deleted.' : 'Name deleted.');
    }

    // ---------- Name helpers (also used by Record Match) ----------

    // Trim and collapse spaces: "  juan   dela cruz " -> "juan dela cruz"
    public static function cleanName(?string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }

    // Same name (not case-sensitive, extra spaces ignored), member or not
    public static function findByName(string $name, $players): ?Player
    {
        $key = mb_strtolower(self::cleanName($name));

        return $players->first(fn ($p) => mb_strtolower(self::cleanName($p->full_name)) === $key);
    }

    // Last word = last name, the rest = first name ("Jay-ar Manalo", "Maria Clara Santos")
    public static function splitName(string $name): array
    {
        $parts = explode(' ', self::cleanName($name));
        $last = count($parts) > 1 ? array_pop($parts) : '';

        return ['first_name' => implode(' ', $parts), 'last_name' => $last];
    }
}