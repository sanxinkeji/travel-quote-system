@extends('layouts.app')

@section('title', '编辑员工 · 旅游报价工作台')

@section('content')
<section class="page-toolbar detail-toolbar"><div><a class="back-link" href="{{ route('users.index') }}"><x-icon name="arrow-left" />返回用户管理</a><h1>编辑员工账号</h1><p>调整姓名、角色和账号状态；密码请在用户列表中单独重置。</p></div></section>
<form class="panel standalone-form" method="POST" action="{{ route('users.update', $user) }}">@csrf @method('PUT')
    <header class="panel-head"><div><h2>{{ $user->name }}</h2><p>登录账号：{{ $user->username }}</p></div></header>
    @include('users._form', ['user' => $user])
    <footer class="form-actions"><a class="btn" href="{{ route('users.index') }}">取消</a><button class="btn primary" type="submit">保存修改</button></footer>
</form>
@endsection
