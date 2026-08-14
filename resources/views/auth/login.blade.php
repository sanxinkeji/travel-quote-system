<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>登录 · 旅游报价工作台</title>
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-brand-panel">
        <div class="login-brand">
            <span class="brand-mark large" aria-hidden="true">旅</span>
            <span>旅游报价工作台</span>
        </div>
        <div class="login-message">
            <h1>把做过的好行程，变成团队共同的方案库。</h1>
            <p>历史报价统一归档、快速检索，找到接近的方案后微调即可发给客户。</p>
        </div>
        <div class="login-brand-foot">内部系统 · 仅限授权员工使用</div>
    </section>

    <section class="login-form-panel">
        <form class="login-card" method="POST" action="{{ Route::has('login.store') ? route('login.store') : url('/login') }}">
            @csrf
            <div class="login-heading">
                <h2>登录工作台</h2>
                <p>请输入管理员分配给您的账号和密码</p>
            </div>

            @if($errors->any())
                <div class="flash error" role="alert">{{ $errors->first() }}</div>
            @endif

            <label class="form-field">
                <span>账号</span>
                <div class="input-with-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                    <input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus placeholder="请输入账号">
                </div>
            </label>
            <label class="form-field">
                <span>密码</span>
                <div class="input-with-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input id="loginPassword" name="password" type="password" autocomplete="current-password" required placeholder="请输入密码">
                    <button class="password-toggle" type="button" data-password-toggle="#loginPassword" aria-label="显示密码" title="显示密码">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="remember" value="1">
                <span>保持登录</span>
            </label>
            <button class="btn primary login-submit" type="submit">登录</button>
        </form>
    </section>
</main>
<script src="{{ asset('js/workspace.js') }}"></script>
</body>
</html>
