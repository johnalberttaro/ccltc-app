# Creates/updates the Players page files for ccltc-app.
# Run from the project root (F:\System Projects\ccltc-app).

$root = Get-Location
$utf8 = New-Object System.Text.UTF8Encoding $false   # UTF-8 without BOM (PHP needs this)

function Save-File($relPath, $content) {
    $full = Join-Path $root $relPath
    $dir = Split-Path $full -Parent
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    [System.IO.File]::WriteAllText($full, $content, $utf8)
    Write-Host "Updated: $relPath" -ForegroundColor Green
}

if (-not (Test-Path (Join-Path $root "artisan"))) {
    Write-Host "Run this from your Laravel project folder (where 'artisan' is)." -ForegroundColor Red
    exit 1
}

# ---------- Controller ----------
Save-File "app\Http\Controllers\PlayerController.php" @'
<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    // Show all players
    public function index()
    {
        $players = Player::orderBy('last_name')->get();

        return view('players.index', compact('players'));
    }

    // Save a new player
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name'  => 'required|string|max:50',
        ]);

        Player::create($validated);

        return redirect()->route('players.index')
                         ->with('success', 'Player added.');
    }

    // Delete a player (only if they have no matches)
    public function destroy(Player $player)
    {
        if ($player->matches()->exists()) {
            return back()->with('error', 'Cannot delete a player who has match records.');
        }

        $player->delete();

        return redirect()->route('players.index')
                         ->with('success', 'Player deleted.');
    }
}
'@

# ---------- Routes ----------
Save-File "routes\web.php" @'
<?php

use App\Http\Controllers\PlayerController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('players.index'));

Route::resource('players', PlayerController::class)
     ->only(['index', 'store', 'destroy']);
'@

# ---------- Layout ----------
Save-File "resources\views\layouts\app.blade.php" @'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CCLTC Tennis')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

    <nav class="bg-green-700 text-white">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="/" class="font-bold text-lg">CCLTC Tennis</a>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('players.index') }}" class="hover:underline">Players</a>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6">
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-100 text-green-800 px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-100 text-red-800 px-4 py-3">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

</body>
</html>
'@

# ---------- Players page ----------
Save-File "resources\views\players\index.blade.php" @'
@extends('layouts.app')

@section('title', 'Players')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Players</h1>

    {{-- Add player form --}}
    <form method="POST" action="{{ route('players.store') }}"
          class="bg-white rounded-xl shadow p-4 mb-6 grid gap-3 md:grid-cols-3 md:items-end">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">First name</label>
            <input type="text" name="first_name" value="{{ old('first_name') }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
            @error('first_name')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Last name</label>
            <input type="text" name="last_name" value="{{ old('last_name') }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
            @error('last_name')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="rounded-lg bg-green-700 text-white font-medium px-4 py-2 hover:bg-green-800">
            Add Player
        </button>
    </form>

    @if ($players->isEmpty())
        <p class="text-gray-500">No players yet.</p>
    @else
        {{-- Phone view: cards --}}
        <div class="grid gap-3 md:hidden">
            @foreach ($players as $player)
                <div class="bg-white rounded-xl shadow p-4 flex items-center justify-between">
                    <span class="font-medium">{{ $player->full_name }}</span>
                    <form method="POST" action="{{ route('players.destroy', $player) }}"
                          onsubmit="return confirm('Delete this player?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-600 text-sm">Delete</button>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Laptop/desktop view: table --}}
        <table class="hidden md:table w-full bg-white rounded-xl shadow overflow-hidden">
            <thead class="bg-gray-100 text-left text-sm">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Added</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($players as $player)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-medium">{{ $player->full_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $player->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('players.destroy', $player) }}"
                                  onsubmit="return confirm('Delete this player?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 text-sm hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
'@

Write-Host ""
Write-Host "Done! Open http://127.0.0.1:8000 to see the Players page." -ForegroundColor Cyan
