<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

// One-time setup page for hosting without a terminal (e.g. free hosting).
// Only works while INSTALL_KEY is set in .env. Open:  https://your-site/install?key=YOUR_KEY
// When everything is green, delete the INSTALL_KEY line from .env and this page turns off.
class InstallController extends Controller
{
    public function show(Request $request)
    {
        $this->guard($request);

        return view('install', $this->state($request) + ['output' => null, 'message' => null]);
    }

    // Step 2: create / update the database tables
    public function migrate(Request $request)
    {
        $this->guard($request);
        @set_time_limit(120);

        try {
            $this->prepareMysql();

            // Nothing real saved yet (no admin account): start from a clean, empty database.
            // This also cleans up tables left half-made by an earlier failed attempt.
            $fresh = ! Schema::hasTable('users') || User::query()->count() === 0;
            $output = '';
            if ($fresh) {
                Artisan::call('db:wipe', ['--force' => true]);
                $output .= trim(Artisan::output()) . "\n";
            }

            Artisan::call('migrate', ['--force' => true]);
            $output = trim($output . Artisan::output());
            $message = ['ok', 'Database tables are ready.'];
        } catch (Throwable $e) {
            $output = $e->getMessage();
            $message = ['error', 'Setting up the database stopped. If it says "timeout", just press the button again.'];
        }

        return view('install', $this->state($request) + compact('output', 'message'));
    }

    // Step 3: first admin account
    public function admin(Request $request)
    {
        $this->guard($request);

        $name = trim((string) $request->input('name'));
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        $message = null;
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = ['error', 'Please enter a name and a valid email.'];
        } elseif (strlen($password) < 8) {
            $message = ['error', 'The password must be at least 8 characters.'];
        } elseif (User::where('email', $email)->exists()) {
            $message = ['error', 'That email already has an account.'];
        } else {
            // The User model hashes the password automatically
            User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $message = ['ok', "Admin account created for $email. You can log in at /admin/login."];
        }

        return view('install', $this->state($request) + ['output' => null, 'message' => $message]);
    }

    // ---------- helpers ----------

    // Free MySQL hosting often makes new tables with the old MyISAM engine, which only allows
    // short index keys ("Specified key was too long"). Use InnoDB and 191-character strings,
    // which work everywhere, and reconnect so the setting is used.
    private function prepareMysql(): void
    {
        $name = config('database.default');
        $driver = config("database.connections.$name.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! config("database.connections.$name.engine")) {
            config(["database.connections.$name.engine" => 'InnoDB']);
        }
        DB::purge($name);
        Schema::defaultStringLength(191);
    }

    private function guard(Request $request): void
    {
        $key = (string) env('INSTALL_KEY', '');

        // Turned off (no key in .env), or a wrong key: behave like the page does not exist
        if (strlen($key) < 8 || ! hash_equals($key, (string) $request->input('key'))) {
            abort(404);
        }
    }

    private function state(Request $request): array
    {
        $checks = [];

        $checks[] = ['PHP version ' . PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>='), 'Needs PHP 8.3 or newer.'];
        $checks[] = ['App key is set', (string) config('app.key') !== '', 'Copy APP_KEY from your computer\'s .env file.'];
        $checks[] = ['Debug mode is off', ! config('app.debug'), 'Set APP_DEBUG=false in .env (so visitors never see error details).'];

        $dbOk = false;
        $dbError = '';
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }
        $checks[] = ['Database connection (' . config('database.default') . ')', $dbOk, $dbError ?: 'Check the DB_ lines in .env.'];

        $tablesOk = $dbOk && Schema::hasTable('migrations') && Schema::hasTable('tournament_matches');
        $pending = 0;
        if ($dbOk) {
            try {
                $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
                $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
                $pending = $files->reject(fn ($m) => in_array($m, $ran, true))->count();
            } catch (Throwable $e) {
                $pending = 1;
            }
        }
        $checks[] = ['Database tables', $tablesOk && $pending === 0, $pending ? "$pending to create or update: press \"Set up database\" below." : 'Press "Set up database" below.'];

        $checks[] = ['storage folder is writable', is_writable(storage_path()) && is_writable(storage_path('framework')), 'Make the storage folder writable.'];

        $uploads = config('filesystems.disks.public.root');
        if (! is_dir($uploads)) {
            @mkdir($uploads, 0755, true);
        }
        $servedDirectly = str_starts_with(realpath($uploads) ?: $uploads, realpath(public_path()) ?: public_path());
        $linked = is_link(public_path('storage')) || is_dir(public_path('storage'));
        $checks[] = ['Uploaded pictures can be shown', is_writable($uploads) && ($servedDirectly || $linked), 'Add UPLOADS_IN_PUBLIC=true to .env.'];

        $admins = $tablesOk ? User::count() : 0;

        return [
            'key' => (string) $request->input('key'),
            'checks' => $checks,
            'dbOk' => $dbOk,
            'tablesOk' => $tablesOk && $pending === 0,
            'admins' => $admins,
        ];
    }
}