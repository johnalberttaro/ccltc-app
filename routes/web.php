<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\TournamentBracketController;
use App\Http\Controllers\Admin\TournamentGroupController;
use App\Http\Controllers\Admin\TournamentMatchController;
use App\Http\Controllers\Admin\TournamentParticipantController;
use App\Http\Controllers\Admin\TournamentScheduleController;
use App\Http\Controllers\Admin\TournamentWorkspaceController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PublicInfoController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TennisMatchController;
use Illuminate\Support\Facades\Route;

/*
| PUBLIC (no login needed)
*/
Route::get('/', [PublicController::class, 'index'])->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/matches/{match}', [PublicController::class, 'show'])->name('matches.show');

Route::get('/events', [PublicInfoController::class, 'events'])->name('events.index');
Route::get('/events/{event}', [PublicInfoController::class, 'event'])->name('events.show');

Route::get('/tournaments', [PublicInfoController::class, 'tournaments'])->name('tournaments.index');
Route::get('/tournaments/{tournament}', [PublicInfoController::class, 'tournament'])->name('tournaments.show');

Route::get('/announcements', [PublicInfoController::class, 'announcements'])->name('announcements.index');
Route::get('/announcements/{announcement}', [PublicInfoController::class, 'announcement'])->name('announcements.show');

/*
| ADMIN (login required)
*/
Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/backup', [BackupController::class, 'download'])->name('backup');

        // Match recording
        Route::get('/matches/create', [TennisMatchController::class, 'create'])->name('matches.create');
        Route::post('/matches', [TennisMatchController::class, 'store'])->name('matches.store');
        Route::get('/matches/{match}/edit', [TennisMatchController::class, 'edit'])->name('matches.edit');
        Route::put('/matches/{match}', [TennisMatchController::class, 'update'])->name('matches.update');
        Route::delete('/matches/{match}', [TennisMatchController::class, 'destroy'])->name('matches.destroy');

        // Members (club members typed by the admin)
        Route::get('/members', [PlayerController::class, 'index'])->name('players.index');
        Route::post('/members', [PlayerController::class, 'store'])->name('players.store');
        Route::put('/members/{player}', [PlayerController::class, 'update'])->name('players.update');
        Route::post('/members/{player}/remove', [PlayerController::class, 'remove'])->name('players.remove');
        Route::delete('/members/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');
        Route::redirect('/players', '/admin/members');

        // Most Wins ranking (fair: same number of official games for everyone) and ranking periods
        Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
        Route::post('/rankings/periods', [RankingController::class, 'store'])->name('rankings.store');
        Route::put('/rankings/periods/{period}', [RankingController::class, 'update'])->name('rankings.update');
        Route::post('/rankings/periods/{period}/activate', [RankingController::class, 'activate'])->name('rankings.activate');
        Route::delete('/rankings/periods/{period}', [RankingController::class, 'destroy'])->name('rankings.destroy');
        Route::get('/rankings/players/{player}', [RankingController::class, 'player'])->name('rankings.player');

        // Events, Tournaments, Announcements management
        Route::resource('events', EventController::class)->except(['show']);
        Route::resource('tournaments', TournamentController::class)->except(['show']);
        Route::resource('announcements', AnnouncementController::class)->except(['show']);
        Route::patch('/announcements/{announcement}/publish', [AnnouncementController::class, 'togglePublish'])->name('announcements.publish');
        Route::patch('/announcements/{announcement}/pin', [AnnouncementController::class, 'togglePin'])->name('announcements.pin');

        // Tournament workspace (tabs) and setup
        Route::prefix('/tournaments/{tournament}')->name('tournaments.')->scopeBindings()->group(function () {
            Route::get('/manage/{tab?}', [TournamentWorkspaceController::class, 'show'])->name('manage');

            Route::post('/participants', [TournamentParticipantController::class, 'store'])->name('participants.store');
            Route::post('/participants/clear-seeds', [TournamentParticipantController::class, 'clearSeeds'])->name('participants.clear-seeds');
            Route::post('/participants/pair', [TournamentParticipantController::class, 'pair'])->name('participants.pair');
            Route::patch('/participants/{participant}', [TournamentParticipantController::class, 'update'])->name('participants.update');
            Route::delete('/participants/{participant}', [TournamentParticipantController::class, 'destroy'])->name('participants.destroy');
            Route::patch('/participants/{participant}/group', [TournamentGroupController::class, 'move'])->name('groups.move');

            Route::post('/groups/generate', [TournamentGroupController::class, 'generate'])->name('groups.generate');
            Route::post('/groups/reshuffle', [TournamentGroupController::class, 'reshuffle'])->name('groups.reshuffle');
            Route::delete('/groups', [TournamentGroupController::class, 'reset'])->name('groups.reset');

            Route::post('/schedule', [TournamentScheduleController::class, 'generate'])->name('schedule.generate');
            Route::delete('/schedule', [TournamentScheduleController::class, 'reset'])->name('schedule.reset');

            Route::post('/bracket', [TournamentBracketController::class, 'generate'])->name('bracket.generate');
            Route::delete('/bracket', [TournamentBracketController::class, 'reset'])->name('bracket.reset');

            Route::put('/matches/{match}/result', [TournamentMatchController::class, 'result'])->name('matches.result');
            Route::delete('/matches/{match}/result', [TournamentMatchController::class, 'clear'])->name('matches.clear');
            Route::patch('/matches/{match}/schedule', [TournamentMatchController::class, 'schedule'])->name('matches.schedule');
        });
    });
});

// Laravel's built-in redirects look for routes named "login" and "dashboard"
Route::get('/login', fn () => redirect()->route('admin.login'))->name('login');
Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))->name('dashboard');

// One-time setup page for hosting without a terminal. Only works while INSTALL_KEY is set in .env.
// No session / CSRF here because the database may not have its tables yet; the key protects it.
Route::withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \Illuminate\Session\Middleware\AuthenticateSession::class,
])->group(function () {
    Route::get('/install', [InstallController::class, 'show'])->name('install');
    Route::post('/install/migrate', [InstallController::class, 'migrate'])->name('install.migrate');
    Route::post('/install/admin', [InstallController::class, 'admin'])->name('install.admin');
});