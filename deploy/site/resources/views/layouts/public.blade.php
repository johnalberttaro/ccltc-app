<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Games') | {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Carmen Copper Lawn Tennis Club (CCLTC) Tennis Records: today\'s games and scores, tournaments, events and announcements.')">
    <meta property="og:title" content="@yield('title', 'Games') | {{ config('app.name') }}">
    <meta property="og:description" content="@yield('description', 'Games, tournaments, events and announcements of the Carmen Copper Lawn Tennis Club (CCLTC).')">
    <meta property="og:image" content="{{ asset('images/logo.png') }}?v=2">
    <meta property="og:type" content="website">
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

    {{-- Skip the loading screen if it was already shown in this browser session --}}
    <script>
        try {
            if (sessionStorage.getItem('splashShown')) {
                document.documentElement.classList.add('splash-seen');
            }
        } catch (e) {}
    </script>

    <style>
        #splash {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #fff;
            background:
                radial-gradient(ellipse at 50% 40%, rgba(134, 239, 172, 0.25), transparent 60%),
                radial-gradient(ellipse at 50% 120%, #052e16, transparent 70%),
                linear-gradient(160deg, #166534, #052e16);
            transition: opacity 0.6s ease, visibility 0.6s ease, transform 0.6s ease;
        }
        html.splash-seen #splash { display: none; }
        #splash.splash-hide { opacity: 0; visibility: hidden; transform: scale(1.08); }

        /* ---------- 3D court ---------- */
        .splash-stage {
            width: 260px;
            height: 270px;
            perspective: 800px;
            perspective-origin: 50% 20%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .splash-court {
            position: relative;
            width: 170px;
            height: 300px;
            transform-style: preserve-3d;
            background: linear-gradient(180deg, #2f9e5b, #1f7a44);
            border: 3px solid #fff;
            border-radius: 4px;
            box-shadow: 0 0 0 10px #145c32, 0 30px 60px rgba(0, 0, 0, 0.6);
            animation: splash-tilt 5s ease-in-out infinite alternate;
        }
        .splash-line { position: absolute; background: #fff; }
        .splash-side-l   { top: 0; bottom: 0; left: 18px;  width: 2px; }
        .splash-side-r   { top: 0; bottom: 0; right: 18px; width: 2px; }
        .splash-service-t { left: 18px; right: 18px; top: 25%;    height: 2px; }
        .splash-service-b { left: 18px; right: 18px; bottom: 25%; height: 2px; }
        .splash-center   { left: calc(50% - 1px); width: 2px; top: 25%; bottom: 25%; }

        .splash-net {
            position: absolute;
            left: -12px;
            right: -12px;
            top: calc(50% - 30px);
            height: 30px;
            transform-origin: bottom center;
            transform: rotateX(-90deg);
            border-top: 4px solid #fff;
            background:
                repeating-linear-gradient(90deg, rgba(255,255,255,0.45) 0 1px, transparent 1px 6px),
                repeating-linear-gradient(0deg, rgba(255,255,255,0.45) 0 1px, transparent 1px 6px),
                rgba(0, 0, 0, 0.15);
        }

        /* ---------- 3D ball ---------- */
        .splash-track {
            position: absolute;
            left: 50%;
            top: 0;
            transform-style: preserve-3d;
            animation: splash-rally 0.9s linear infinite alternate;
        }
        .splash-height {
            transform-style: preserve-3d;
            animation: splash-arc 0.9s ease-in-out infinite;
        }
        .splash-ball {
            position: absolute;
            width: 30px;
            height: 30px;
            margin: -15px 0 0 -15px;
            border-radius: 50%;
            transform: rotateX(-62deg);
            background: radial-gradient(circle at 32% 28%, #fbffd0 0%, #e3f04a 30%, #b5c42a 70%, #7d8a12 100%);
            box-shadow: inset -4px -5px 8px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .splash-ball svg {
            width: 100%;
            height: 100%;
            animation: splash-spin 0.6s linear infinite;
        }
        .splash-ball-shadow {
            position: absolute;
            width: 30px;
            height: 30px;
            margin: -15px 0 0 -15px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,0,0,0.55), transparent 70%);
            animation: splash-shadow 0.9s ease-in-out infinite;
        }

        /* ---------- Text ---------- */
        .splash-title {
            margin-top: 8px;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #f0fdf4;
            text-shadow:
                0 1px 0 #bbf7d0, 0 2px 0 #86efac, 0 3px 0 #4ade80,
                0 4px 0 #22c55e, 0 5px 0 #16a34a, 0 6px 0 #15803d,
                0 12px 18px rgba(0, 0, 0, 0.5);
            animation: splash-float 2.4s ease-in-out infinite alternate;
        }
        .splash-sub { color: #bbf7d0; font-size: 0.9rem; margin-top: 10px; }
        .splash-bar {
            width: 190px;
            height: 6px;
            margin-top: 22px;
            border-radius: 9999px;
            background: rgba(0, 0, 0, 0.3);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5), 0 1px 0 rgba(255, 255, 255, 0.15);
            overflow: hidden;
        }
        .splash-bar span {
            display: block;
            height: 100%;
            width: 0;
            border-radius: 9999px;
            background: linear-gradient(180deg, #ecfccb, #a3e635);
            animation: splash-fill 2s ease-out forwards;
        }

        /* ---------- Animations ---------- */
        @keyframes splash-tilt {
            from { transform: rotateX(58deg) rotateZ(-10deg); }
            to   { transform: rotateX(58deg) rotateZ(10deg); }
        }
        @keyframes splash-rally {
            from { transform: translateY(262px); }
            to   { transform: translateY(38px); }
        }
        @keyframes splash-arc {
            0%   { transform: translateZ(14px); }
            50%  { transform: translateZ(95px); }
            100% { transform: translateZ(14px); }
        }
        @keyframes splash-shadow {
            0%, 100% { transform: scale(1);    opacity: 0.8; }
            50%      { transform: scale(0.55); opacity: 0.3; }
        }
        @keyframes splash-spin { to { transform: rotate(360deg); } }
        @keyframes splash-float {
            from { transform: translateY(0); }
            to   { transform: translateY(-4px); }
        }
        @keyframes splash-fill { to { width: 100%; } }

        @media (prefers-reduced-motion: reduce) {
            #splash * { animation-duration: 0s !important; animation-iteration-count: 1 !important; }
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v=6">
</head>
<body class="app-body has-footer bg-gray-50 text-gray-900 min-h-screen">

    {{-- Loading screen (3D court + bouncing ball) --}}
    <div id="splash" aria-hidden="true">
        <div class="splash-stage">
            <div class="splash-court">
                <div class="splash-line splash-side-l"></div>
                <div class="splash-line splash-side-r"></div>
                <div class="splash-line splash-service-t"></div>
                <div class="splash-line splash-service-b"></div>
                <div class="splash-line splash-center"></div>
                <div class="splash-net"></div>

                <div class="splash-track">
                    <div class="splash-ball-shadow"></div>
                    <div class="splash-height">
                        <div class="splash-ball">
                            <svg viewBox="0 0 100 100">
                                <path d="M8 28 C 36 40, 36 60, 8 72" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round"/>
                                <path d="M92 28 C 64 40, 64 60, 92 72" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h1 class="splash-title">{{ config('app.name') }}</h1>
        <p class="splash-sub">Loading match records...</p>
        <div class="splash-bar"><span></span></div>
    </div>

    @php
        $navLinks = [
            ['route' => 'home',                'label' => 'Games',         'icon' => 'ball',      'active' => ['home', 'matches.*']],
            ['route' => 'events.index',        'label' => 'Events',        'icon' => 'calendar',  'active' => ['events.*']],
            ['route' => 'tournaments.index',   'label' => 'Tournaments',   'icon' => 'trophy',    'active' => ['tournaments.*']],
            ['route' => 'announcements.index', 'label' => 'Announcements', 'icon' => 'megaphone', 'active' => ['announcements.*']],
        ];
    @endphp

    <nav class="bg-green-700 text-white sticky top-0 z-40 shadow">
        <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-lg">
                <img src="{{ asset('images/logo-160.png') }}?v=2" alt="CCLTC" class="w-10 h-10 shrink-0 drop-shadow-sm" width="40" height="40">
                {{ config('app.name') }}
            </a>

            {{-- Desktop links --}}
            <div class="hidden md:flex items-center gap-1 text-sm">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 {{ request()->routeIs(...$link['active']) ? 'bg-white/15 font-semibold' : 'hover:bg-white/10' }}">
                        <x-icon :name="$link['icon']" class="w-4 h-4" /> {{ $link['label'] }}
                    </a>
                @endforeach

                @auth
                    <a href="{{ route('admin.dashboard') }}"
                       class="ml-2 rounded-lg bg-white text-green-800 font-medium px-3 py-1.5 hover:bg-green-50">Dashboard</a>
                @else
                    <a href="{{ route('admin.login') }}" class="ml-2 rounded-lg px-3 py-2 text-green-100 hover:text-white hover:bg-white/10">Admin</a>
                @endauth
            </div>

            <div class="flex items-center gap-1">
            {{-- Install as an app (shown only when the phone / browser supports it) --}}
            <button type="button" id="install-app" hidden class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1.5 text-sm font-medium hover:bg-white/20" title="Install the app on this device">
                <x-icon name="download" class="w-4 h-4" /> <span>Install</span>
            </button>
            <button type="button" class="theme-toggle rounded-lg p-2 hover:bg-white/10" aria-label="Switch dark / light mode" title="Switch dark / light mode">
                <x-icon name="moon" class="icon-moon w-5 h-5" />
                <x-icon name="sun" class="icon-sun w-5 h-5" />
            </button>
                {{-- Mobile hamburger button --}}
                <button type="button" id="menu-button" class="md:hidden rounded-lg p-2 hover:bg-white/10"
                        aria-controls="mobile-menu" aria-expanded="false" aria-label="Open menu">
                    <x-icon name="menu" class="w-6 h-6" id="menu-icon-open" />
                    <x-icon name="x" class="w-6 h-6 hidden" id="menu-icon-close" />
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div id="mobile-menu" class="md:hidden hidden border-t border-white/15 bg-green-800">
            <div class="px-4 py-3 grid gap-1">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-3 {{ request()->routeIs(...$link['active']) ? 'bg-white/15 font-semibold' : 'hover:bg-white/10' }}">
                        <x-icon :name="$link['icon']" class="w-5 h-5" /> {{ $link['label'] }}
                    </a>
                @endforeach

                <div class="border-t border-white/15 mt-2 pt-2">
                    @auth
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-3 hover:bg-white/10">
                            <x-icon name="grid" class="w-5 h-5" /> Admin Dashboard
                        </a>
                    @else
                        <a href="{{ route('admin.login') }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-green-100 hover:bg-white/10">
                            <x-icon name="users" class="w-5 h-5" /> Admin login
                        </a>
                    @endauth
                </div>
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

    {{-- Footer: shown on the Games page only --}}
    @if (request()->routeIs('home', 'matches.*'))
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div>
                <a href="{{ route('home') }}" class="site-footer__brand" style="text-decoration:none">
                    <img src="{{ asset('images/logo-160.png') }}?v=2" alt="" width="48" height="48" loading="lazy">
                    <span>
                        <span class="site-footer__name block">CCLTC</span>
                        <span class="site-footer__club block">Carmen Copper Lawn Tennis Club</span>
                    </span>
                </a>
                <p class="site-footer__about">Game results, tournaments, events and club news. Games are recorded by the court admin.</p>
            </div>

            <div>
                <h2>Explore</h2>
                <ul>
                    @foreach ($navLinks as $link)
                        <li><a href="{{ route($link['route']) }}"><x-icon :name="$link['icon']" /> {{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2>Club</h2>
                <ul>
                    <li data-install-item hidden>
                        <button type="button" class="site-footer__install" data-install-app><x-icon name="download" /> Install the app</button>
                    </li>
                    @auth
                        <li><a href="{{ route('admin.dashboard') }}"><x-icon name="grid" /> Admin dashboard</a></li>
                    @else
                        <li><a href="{{ route('admin.login') }}"><x-icon name="lock" /> Admin login</a></li>
                    @endauth
                </ul>
            </div>
        </div>

        <div class="site-footer__bottom">
            <div class="site-footer__bottom-inner">
                <span>&copy; {{ now()->year }} Carmen Copper Lawn Tennis Club. All rights reserved.</span>
                <a href="#" class="site-footer__top" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;">Back to top &uarr;</a>
            </div>
        </div>
    </footer>
    @endif

    {{-- Hide the loading screen once the page is ready (shown for at least 2.2 seconds) --}}
    <script>
        (function () {
            var splash = document.getElementById('splash');
            if (!splash || document.documentElement.classList.contains('splash-seen')) return;

            var started = Date.now();
            var minTime = 2200;

            function hideSplash() {
                var wait = Math.max(0, minTime - (Date.now() - started));
                setTimeout(function () {
                    splash.classList.add('splash-hide');
                    try { sessionStorage.setItem('splashShown', '1'); } catch (e) {}
                    setTimeout(function () { splash.remove(); }, 700);
                }, wait);
            }

            if (document.readyState === 'complete') {
                hideSplash();
            } else {
                window.addEventListener('load', hideSplash);
            }
        })();
    </script>

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
    {{-- iPhone / iPad: how to install (Safari has no install button of its own) --}}
    <dialog id="install-ios" class="m-auto rounded-2xl p-0 w-[min(92vw,24rem)] shadow-2xl backdrop:bg-gray-900/50">
        <div class="p-5 grid gap-3 text-sm">
            <div class="flex items-center gap-3">
                <img src="{{ asset('icons/icon-192.png') }}?v=2" alt="" class="w-12 h-12 rounded-xl">
                <div>
                    <p class="font-bold text-base">Install CCLTC</p>
                    <p class="text-gray-500">Add it to your Home Screen like an app.</p>
                </div>
            </div>
            <ol class="grid gap-2 list-decimal pl-5 text-gray-700">
                <li>Tap the <strong>Share</strong> button <x-icon name="share" class="inline w-4 h-4 align-text-bottom" /> at the bottom of Safari.</li>
                <li>Scroll down and tap <strong>Add to Home Screen</strong>.</li>
                <li>Tap <strong>Add</strong>.</li>
            </ol>
            <button type="button" data-close-install class="mt-1 rounded-lg bg-green-700 text-white font-semibold px-4 py-2.5 hover:bg-green-800">OK</button>
        </div>
    </dialog>

    {{-- Install button logic --}}
    <script>
        (function () {
            var top = document.getElementById('install-app');
            var buttons = [];
            if (top) buttons.push(top);
            document.querySelectorAll('[data-install-app]').forEach(function (b) { buttons.push(b); });
            if (!buttons.length) return;
            var installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            if (installed) return;

            function show(visible) {
                if (top) top.hidden = !visible;
                document.querySelectorAll('[data-install-item]').forEach(function (li) { li.hidden = !visible; });
            }

            var deferred = null;
            // Android / Chrome / Edge: the browser tells us when installing is possible
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferred = e;
                show(true);
            });
            window.addEventListener('appinstalled', function () { show(false); deferred = null; });

            // iPhone / iPad (Safari): show the steps instead
            var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            var dialog = document.getElementById('install-ios');
            if (isIos && dialog) show(true);

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (deferred) {
                        deferred.prompt();
                        deferred.userChoice.finally(function () { deferred = null; show(false); });
                    } else if (isIos && dialog) {
                        dialog.showModal();
                    }
                });
            });
            if (dialog) {
                dialog.querySelector('[data-close-install]').addEventListener('click', function () { dialog.close(); });
                dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
            }
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