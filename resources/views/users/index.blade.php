@extends('layouts.app')

@section('title', '用户管理 · 旅游报价工作台')

@section('content')
@php($userRows = $users ?? collect())
<section class="page-toolbar">
    <div><h1>用户管理</h1><p>管理员工账号、角色和使用状态；停用账号不会影响其历史报价。</p></div>
    <a class="btn primary" href="{{ route('users.create') }}"><x-icon name="plus" />新增员工</a>
</section>

<section class="user-stats">
    <div><span>全部账号</span><strong>{{ method_exists($userRows, 'total') ? $userRows->total() : count($userRows) }}</strong></div>
    <div><span>正常使用</span><strong>{{ collect($userRows->items() ?? $userRows)->where('is_active', true)->count() }}</strong></div>
    <div><span>管理员</span><strong>{{ collect($userRows->items() ?? $userRows)->where('role', 'admin')->count() }}</strong></div>
</section>

<section class="panel user-filter-panel">
    <form class="user-filters" method="GET" action="{{ route('users.index') }}">
        <label><span>搜索员工</span><input name="q" value="{{ request('q') }}" placeholder="姓名或登录账号"></label>
        <label><span>角色</span><select name="role"><option value="">全部角色</option><option value="admin" @selected(request('role') === 'admin')>管理员</option><option value="employee" @selected(request('role') === 'employee')>普通员工</option></select></label>
        <label><span>状态</span><select name="status"><option value="">全部状态</option><option value="active" @selected(request('status') === 'active')>启用</option><option value="inactive" @selected(request('status') === 'inactive')>停用</option></select></label>
        <div class="filter-actions">
            <button class="icon-btn primary" type="submit" data-tooltip="筛选员工" aria-label="筛选员工"><x-icon name="search" /></button>
            <a class="icon-btn" href="{{ route('users.index') }}" data-tooltip="清空筛选" aria-label="清空筛选"><x-icon name="reset" /></a>
        </div>
    </form>
</section>

<section class="panel table-panel">
    <header class="panel-head"><div><h2>员工账号</h2><p>普通员工可共享全部历史报价，只能修改自己创建的原始报价。</p></div></header>
    <div class="data-table-wrap">
        <table class="data-table user-table">
            <thead><tr><th>员工</th><th>登录账号</th><th>角色</th><th>状态</th><th>创建时间</th><th>最近登录</th><th class="actions-cell">操作</th></tr></thead>
            <tbody>
            @forelse($userRows as $user)
                <tr>
                    <td><div class="user-identity"><span class="avatar table-avatar">{{ mb_substr($user->name ?? $user->username, 0, 1) }}</span><strong>{{ $user->name ?? '-' }}</strong>@if(auth()->id() === $user->id)<em>当前账号</em>@endif</div></td>
                    <td>{{ $user->username }}</td>
                    <td><span class="role-badge {{ $user->role }}">{{ $user->role === 'admin' ? '管理员' : '普通员工' }}</span></td>
                    <td><span class="status-badge {{ $user->is_active ? 'active' : 'inactive' }}"><i></i>{{ $user->is_active ? '启用' : '停用' }}</span></td>
                    <td>{{ $user->created_at?->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? '尚未登录' }}</td>
                    <td class="actions-cell"><div class="row-actions">
                        <a class="icon-btn" href="{{ route('users.edit', $user) }}" data-tooltip="编辑员工" aria-label="编辑员工"><x-icon name="user-edit" /></a>
                        <button class="icon-btn" type="button" data-open-modal="password-{{ $user->id }}" data-tooltip="重置密码" aria-label="重置密码"><x-icon name="key" /></button>
                        @if(auth()->id() !== $user->id)
                            <form method="POST" action="{{ route('users.status', $user) }}" data-confirm="确定{{ $user->is_active ? '停用' : '启用' }}该员工账号吗？">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}"><button class="icon-btn {{ $user->is_active ? 'danger' : 'success' }}" data-tooltip="{{ $user->is_active ? '停用账号' : '启用账号' }}" aria-label="{{ $user->is_active ? '停用账号' : '启用账号' }}"><x-icon name="toggle" /></button></form>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><x-icon name="user-edit" /><strong>还没有员工账号</strong><span>创建员工后即可一起使用报价系统。</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($userRows, 'links'))<div class="pagination-wrap">{{ $userRows->withQueryString()->links() }}</div>@endif
</section>

@foreach($userRows as $user)
<div class="modal-backdrop" data-modal="password-{{ $user->id }}" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="password-title-{{ $user->id }}">
        <header><div><h2 id="password-title-{{ $user->id }}">重置登录密码</h2><p>{{ $user->name }}（{{ $user->username }}）</p></div><button class="icon-btn" type="button" data-close-modal aria-label="关闭">×</button></header>
        <form method="POST" action="{{ route('users.password', $user) }}">@csrf @method('PUT')
            <div class="modal-body">
                <label class="form-field"><span>新密码</span><input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="至少 8 位"></label>
                <label class="form-field"><span>确认新密码</span><input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" placeholder="再次输入新密码"></label>
            </div>
            <footer><button class="btn" type="button" data-close-modal>取消</button><button class="btn primary" type="submit">确认重置</button></footer>
        </form>
    </section>
</div>
@endforeach
@endsection
