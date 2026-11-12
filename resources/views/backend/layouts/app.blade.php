<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <!-- FontAwesome (ให้ไอคอนหน้า backend แสดงผลเหมือนหน้าหลัก) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .admin-hero { background: linear-gradient(135deg,#334155 0%,#475569 55%,#64748b 100%); border-radius:14px; color:#fff; }
        .admin-card { border:0; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.06); }
        .admin-table thead th { background:#f1f5f9; color:#334155; font-size:.78rem; text-transform:uppercase;
                                letter-spacing:.02em; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
        .admin-table tbody tr:hover { background:#f8fafc; }
        .admin-table td { vertical-align:middle; }
    </style>

</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @auth()
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarScrollingDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    Access Control
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">
                                    @can('view_users')
                                        <li><a class="dropdown-item" href="{{ route('users.index') }}">
                                                😎 Users
                                            </a>
                                        </li>
                                    @endcan
                                    @can('view_roles')
                                    <li><a class="dropdown-item" href="{{ route('roles.index') }}">
                                        🔒 Roles
                                        </a>
                                    </li>
                                    @endcan
                                </ul>
                            </li>
                        @endauth

                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="{{ route('config.index') }}">Config</a>
                        </li>
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else


                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarScrollingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">
                                @role('Admin')
                                    <li><a class="dropdown-item" href="/backend">Backend</a></li>
                                 @endrole
                                 <li><a class="dropdown-item" href="/home">home</a></li>
                                  <li><hr class="dropdown-divider"></li>
                                  <li>
                                    <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault();
                                                         document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>
                                      <form id="logout-form" action="{{ route('logout') }}" method="POST"class="d-none">
                                        @csrf
                                    </form>                                   
                                    </li>
                                </ul>
                              </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        {{-- <main class="py-4">
            @yield('content')
        </main> --}}

        <main class="py-4">
            <div class="container">
                <div id="flash-msg">
                    @include('partials.flash_message')
                    @include('flash::message')
                </div>
                @yield('content')
            </div>
        </main>

    </div>
</body>

</html>
