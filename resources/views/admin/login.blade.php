@extends('layouts.public')

@section('title', 'Admin Login')

@section('content')
    <div class="max-w-md mx-auto mt-4 md:mt-10">
        <div class="bg-white rounded-2xl shadow p-6">
            <img src="{{ asset('images/logo.png') }}?v=2" alt="Carmen Copper Lawn Tennis Club" class="w-28 h-28 mx-auto -mt-1 mb-3" width="112" height="112">
            <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Admin only</p>
            <h1 class="text-2xl font-bold">Admin Login</h1>
            <p class="text-sm text-gray-500 mb-5">Log in to record games and manage players.</p>

            <form method="POST" action="{{ route('admin.login.submit') }}" class="grid gap-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('email')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('password')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" class="rounded border-gray-300">
                    Remember me
                </label>

                <button type="submit"
                        class="rounded-lg bg-green-700 text-white font-semibold px-4 py-3 hover:bg-green-800">
                    Login
                </button>
            </form>

            <p class="text-sm text-gray-500 mt-5 text-center">
                Just want to see scores?
                <a href="{{ route('home') }}" class="text-green-700 font-medium hover:underline">View today's games</a>
            </p>
        </div>
    </div>
@endsection