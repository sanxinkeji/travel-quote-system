<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '旅游报价工作台')</title>
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}?v={{ filemtime(public_path('css/workspace.css')) }}">
    @stack('styles')
</head>
<body>
<div class="route-progress" data-route-progress aria-hidden="true"><span></span></div>
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="{{ Route::has('quotes.index') ? route('quotes.index') : url('/quotes') }}">
            <span class="brand-mark" aria-hidden="true">旅</span>
            <span>旅游报价工作台</span>
        </a>
        <div class="account-area">
            <span class="role-label">{{ auth()->user()?->role === 'admin' ? '管理员' : '员工' }}</span>
            <div class="account-menu" data-dropdown>
                <button class="account-trigger" type="button" data-dropdown-trigger aria-expanded="false">
                    <span class="avatar">{{ mb_substr(auth()->user()?->name ?? auth()->user()?->username ?? '用', 0, 1) }}</span>
                    <span class="account-name">{{ auth()->user()?->name ?? auth()->user()?->username ?? '当前用户' }}</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                </button>
                <div class="dropdown-menu" data-dropdown-menu hidden>
                    <div class="dropdown-summary">
                        <strong>{{ auth()->user()?->name ?? '当前用户' }}</strong>
                        <span>{{ auth()->user()?->username }}</span>
                    </div>
                    @if(Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-action" type="submit">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg>
                                退出登录
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <div class="workspace">
        <aside class="sidebar" aria-label="主导航">
            <div class="nav-section">
                <span class="nav-label">工作台</span>
                <a class="nav-item {{ request()->routeIs('quotes.*') ? 'active' : '' }}" href="{{ Route::has('quotes.index') ? route('quotes.index') : url('/quotes') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2M8 11h6M11 8v6"/></svg>
                    <span>历史报价库</span>
                </a>
                @if(auth()->user()?->role === 'admin')
                    <a class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ Route::has('users.index') ? route('users.index') : url('/users') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                        <span>用户管理</span>
                    </a>
                @endif
            </div>
            <div class="sidebar-foot">
                <span class="status-dot"></span>
                <span>数据已连接</span>
            </div>
        </aside>

        <main class="main-content">
            @if(session('success'))
                <div class="flash success" role="status">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="flash error" role="alert">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="flash error" role="alert">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<div class="toast" data-toast role="status" aria-live="polite"></div>
<script src="{{ asset('js/workspace.js') }}?v={{ filemtime(public_path('js/workspace.js')) }}"></script>
@stack('scripts')
</body>
</html>
