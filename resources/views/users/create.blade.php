@extends('layouts.app')

@section('title', '新增员工 · 旅游报价工作台')

@section('content')
<section class="page-toolbar detail-toolbar"><div><a class="back-link" href="{{ route('users.index') }}"><x-icon name="arrow-left" />返回用户管理</a><h1>新增员工账号</h1><p>管理员设置账号和密码后，员工即可直接登录使用。</p></div></section>
<form class="panel standalone-form" method="POST" action="{{ route('users.store') }}">@csrf
    <header class="panel-head"><div><h2>账号信息</h2><p>系统不会要求员工首次登录时强制修改密码。</p></div></header>
    @include('users._form', ['user' => new \App\Models\User])
    <footer class="form-actions"><a class="btn" href="{{ route('users.index') }}">取消</a><button class="btn primary" type="submit">创建账号</button></footer>
</form>
@endsection
