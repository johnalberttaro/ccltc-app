<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | Admin | {{ config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow">
    {{-- App (PWA): icon, colors, install support --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}" crossorigin="use-credentials">
    <meta name="theme-color" content="#15803d">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('icons/favicon-32.png') }}?v=2" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}?v=2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="CCLTC">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    {{-- Fonts + theme (dark/light). The script runs first so the page never flashes the wrong theme. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <script>
        try {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v=6">
</head>
<body class="app-body has-footer bg-gray-100 text-gray-900 min-h-screen">

    @php
        $adminLinks = [
            ['route' => 'admin.dashboard',           'label' => 'Dashboard',     'icon' => 'grid',      'active' => ['admin.dashboard']],
            ['route' => 'admin.matches.create',      'label' => 'Record Match',  'icon' => 'ball',      'active' => ['admin.matches.*']],
            ['route' => 'admin.players.index',       'label' => 'Members',       'icon' => 'users',     'active' => ['admin.players.*']],
            ['route' => 'admin.events.index',        'label' => 'Events',        'icon' => 'calendar',  'active' => ['admin.events.*']],
            ['route' => 'admin.tournaments.index',   'label' => 'Tournaments',   'icon' => 'trophy',    'active' => ['admin.tournaments.*']],
            ['route' => 'admin.announcements.index', 'label' => 'Announcements', 'icon' => 'megaphone', 'active' => ['admin.announcements.*']],
        ];
    @endphp

    <nav class="bg-gray-900 text-white sticky top-0 z-40 shadow">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between gap-3">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 font-bold">
                <img src="{{ asset('images/logo-160.png') }}?v=2" alt="CCLTC" class="w-9 h-9 shrink-0 drop-shadow-sm" width="36" height="36">
                <span class="hidden xl:inline whitespace-nowrap">{{ config('app.name') }}</span>
                <span class="rounded bg-green-500 text-gray-900 text-[11px] font-bold uppercase tracking-wide px-2 py-0.5">Admin</span>
            </a>

            {{-- Desktop links --}}
            <div class="hidden lg:flex items-center gap-1 text-sm">
                @foreach ($adminLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-2 {{ request()->routeIs(...$link['active']) ? 'bg-white/15 text-green-300 font-semibold' : 'text-gray-200 hover:bg-white/10' }}">
                        <x-icon :name="$link['icon']" class="w-4 h-4" /> {{ $link['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="hidden lg:flex items-center gap-3 text-sm">
                <a href="{{ route('home') }}" target="_blank" class="inline-flex items-center gap-1 text-gray-400 hover:text-white" title="Open public page">
                    <x-icon name="external" class="w-4 h-4" /> Public
                </a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 hover:bg-white/20" title="Logout {{ auth()->user()->name }}">
                        <x-icon name="logout" class="w-4 h-4" /> Logout
                    </button>
                </form>
            </div>

            <div class="flex items-center gap-1">
            <button type="button" class="theme-toggle rounded-lg p-2 hover:bg-white/10" aria-label="Switch dark / light mode" title="Switch dark / light mode">
                <x-icon name="moon" class="icon-moon w-5 h-5" />
                <x-icon name="sun" class="icon-sun w-5 h-5" />
            </button>
                {{-- Mobile hamburger button --}}
                <button type="button" id="menu-button" class="lg:hidden rounded-lg p-2 hover:bg-white/10"
                        aria-controls="mobile-menu" aria-expanded="false" aria-label="Open menu">
                    <x-icon name="menu" class="w-6 h-6" id="menu-icon-open" />
                    <x-icon name="x" class="w-6 h-6 hidden" id="menu-icon-close" />
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div id="mobile-menu" class="lg:hidden hidden border-t border-white/10 bg-gray-800">
            <div class="px-4 py-3 grid gap-1">
                <p class="px-3 pb-2 text-xs text-gray-400">Signed in as {{ auth()->user()->name }}</p>
                @foreach ($adminLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-3 {{ request()->routeIs(...$link['active']) ? 'bg-white/15 text-green-300 font-semibold' : 'hover:bg-white/10' }}">
                        <x-icon :name="$link['icon']" class="w-5 h-5" /> {{ $link['label'] }}
                    </a>
                @endforeach

                <div class="border-t border-white/10 mt-2 pt-2 grid gap-1">
                    <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-3 text-gray-300 hover:bg-white/10">
                        <x-icon name="external" class="w-5 h-5" /> View public page
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="w-full flex items-center gap-3 rounded-lg px-3 py-3 text-left text-red-300 hover:bg-white/10">
                            <x-icon name="logout" class="w-5 h-5" /> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
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

    {{-- Footer: shown on the Dashboard only --}}
    @if (request()->routeIs('admin.dashboard'))
    <footer class="site-footer site-footer--admin">
        <div class="site-footer__inner">
            <div>
                <a href="{{ route('admin.dashboard') }}" class="site-footer__brand" style="text-decoration:none">
                    <img src="{{ asset('images/logo-160.png') }}?v=2" alt="" width="48" height="48" loading="lazy">
                    <span>
                        <span class="site-footer__name block">CCLTC Admin</span>
                        <span class="site-footer__club block">Carmen Copper Lawn Tennis Club</span>
                    </span>
                </a>
                <p class="site-footer__about">Signed in as {{ auth()->user()->name }}. Record games, manage members, events, tournaments and announcements.</p>
            </div>

            <div>
                <h2>Manage</h2>
                <ul>
                    @foreach ($adminLinks as $link)
                        <li><a href="{{ route($link['route']) }}"><x-icon :name="$link['icon']" /> {{ $link['label'] }}</a></li>
                    @endforeach
                    <li><a href="{{ route('admin.games.index') }}"><x-icon name="list" /> All games (any date)</a></li>
                </ul>
            </div>

            <div>
                <h2>Account</h2>
                <ul>
                    <li><a href="{{ route('home') }}" target="_blank"><x-icon name="external" /> View public page</a></li>
                    <li>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button class="site-footer__install"><x-icon name="logout" /> Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

        <div class="site-footer__bottom">
            <div class="site-footer__bottom-inner">
                <span class="flex items-center gap-2">
                    <span>&copy; {{ now()->year }} Carmen Copper Lawn Tennis Club. All rights reserved.</span>
                    {{-- Small database backup button (downloads a .sql file) --}}
                    <form method="POST" action="{{ route('admin.backup') }}" class="inline-flex" data-no-loading>
                        @csrf
                        <button type="submit" class="site-footer__install" style="opacity:.35" title="Backup" aria-label="Download database backup"><x-icon name="download" class="w-4 h-4" /></button>
                    </form>
                </span>
                <a href="#" class="site-footer__top" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;">Back to top &uarr;</a>
            </div>
        </div>
    </footer>
    @endif

    {{-- Hamburger menu toggle --}}
    <script>
        (function () {
            var button = document.getElementById('menu-button');
            var menu = document.getElementById('mobile-menu');
            if (!button || !menu) return;

            button.addEventListener('click', function () {
                var isOpen = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', isOpen);
                document.getElementById('menu-icon-open').classList.toggle('hidden', !isOpen);
                document.getElementById('menu-icon-close').classList.toggle('hidden', isOpen);
                button.setAttribute('aria-expanded', String(!isOpen));
            });
        })();
    </script>

    {{-- Shared confirmation dialog: add data-confirm="Title" to any form --}}
    <dialog id="confirm-dialog" class="m-auto rounded-2xl p-0 w-[min(92vw,28rem)] shadow-2xl backdrop:bg-gray-900/50">
        <div class="p-5">
            <div class="flex items-start gap-3">
                <span id="confirm-icon" class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 bg-green-100 text-green-700">
                    <x-icon name="info" class="w-6 h-6" />
                </span>
                <div class="min-w-0">
                    <h2 id="confirm-title" class="text-lg font-bold text-gray-900"></h2>
                    <p id="confirm-body" class="mt-1 text-sm text-gray-600 whitespace-pre-line"></p>
                </div>
            </div>
            <div class="mt-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <button type="button" id="confirm-cancel" class="rounded-lg px-4 py-2.5 text-gray-700 hover:bg-gray-100 font-medium">Cancel</button>
                <button type="button" id="confirm-ok" class="rounded-lg px-4 py-2.5 text-white font-semibold bg-green-700 hover:bg-green-800">Continue</button>
            </div>
        </div>
    </dialog>

    <script>
        (function () {
            var dialog = document.getElementById('confirm-dialog');
            var pendingForm = null;

            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (e.defaultPrevented) return;

                // Ask first if the form wants a confirmation
                if (form.dataset.confirm && !form.dataset.confirmed && dialog && dialog.showModal) {
                    e.preventDefault();
                    pendingForm = form;
                    var danger = form.dataset.confirmTone === 'danger';
                    document.getElementById('confirm-title').textContent = form.dataset.confirm;
                    document.getElementById('confirm-body').textContent = form.dataset.confirmBody || '';
                    var ok = document.getElementById('confirm-ok');
                    ok.textContent = form.dataset.confirmButton || 'Continue';
                    ok.className = 'rounded-lg px-4 py-2.5 text-white font-semibold ' + (danger ? 'bg-red-600 hover:bg-red-700' : 'bg-green-700 hover:bg-green-800');
                    document.getElementById('confirm-icon').className = 'w-10 h-10 rounded-full flex items-center justify-center shrink-0 ' + (danger ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700');
                    dialog.showModal();
                    return;
                }

                // Downloads (e.g. backup) stay on the page, so no loading state for them
                if (form.hasAttribute('data-no-loading')) return;

                // Loading state: stop double clicks while the page saves
                form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (btn) {
                    setTimeout(function () {
                        btn.disabled = true;
                        btn.classList.add('opacity-60', 'cursor-wait');
                        if (btn.dataset.loading) btn.textContent = btn.dataset.loading;
                    }, 0);
                });
            });

            if (dialog) {
                document.getElementById('confirm-cancel').addEventListener('click', function () { dialog.close(); pendingForm = null; });
                document.getElementById('confirm-ok').addEventListener('click', function () {
                    dialog.close();
                    if (pendingForm) {
                        pendingForm.dataset.confirmed = '1';
                        pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
                    }
                });
            }
        })();
    </script>

    {{-- Dark / light mode switch --}}
    <script>
        (function () {
            document.querySelectorAll('.theme-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var dark = document.documentElement.classList.toggle('dark');
                    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
                });
            });
        })();
    </script>
    {{-- App (PWA): register the service worker (needs https; skipped on plain http except localhost) --}}
    <script>
        if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
            window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
        }
    </script>
</body>
</html>