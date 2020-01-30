<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen antialiased leading-none">
    <div id="app">
        <div class="flex">
            <div>
                <div class="bg-blue-900 p-6">
                    <a href="{{ url('/home') }}" class="text-lg font-semibold text-gray-100 no-underline">
                        {{ config('app.name', 'Laravel') }}
                    </a>
                </div>
                <div class="p-6 py-8 bg-blue-800 text-blue-100 w-64 h-screen">
                    <div class="mb-8">
                        <a href="{{ route('home') }}">Dashboard</a>
                    </div>

                    <div class="text-sm text-blue-200 uppercase font-bold tracking-widest">Projects</div>
                    <ul class="mt-4">
                        @foreach (Auth::user()->currentTeam->projects as $project)
                            <li>
                                <a href="{{ route('projects.show', [$project]) }}">{{ $project->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="w-full">
                <div class="text-right p-6 mb-8 bg-white">
                    <span class="text-sm pr-4">{{ Auth::user()->name }}</span>

                    <a href="{{ route('logout') }}"
                        class="no-underline hover:underline text-sm p-3"
                        onclick="event.preventDefault();
                            document.getElementById('logout-form').submit();">{{ __('Logout') }}</a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        {{ csrf_field() }}
                    </form>
                </div>
                <div class="flex items-center">
                    <div class="md:w-1/2 md:mx-auto">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="{{ mix('js/app.js') }}"></script>
</body>
</html>
