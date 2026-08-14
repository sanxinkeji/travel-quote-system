@php($editing = isset($user) && $user->exists)
<div class="user-form-grid">
    <label><span>员工姓名</span><input name="name" value="{{ old('name', $user->name ?? '') }}" required placeholder="请输入员工姓名"></label>
    <label><span>登录账号</span><input name="username" value="{{ old('username', $user->username ?? '') }}" required autocomplete="off" placeholder="建议使用姓名拼音"></label>
    <label><span>角色权限</span><select name="role" required><option value="employee" @selected(old('role', $user->role ?? 'employee') === 'employee')>普通员工</option><option value="admin" @selected(old('role', $user->role ?? '') === 'admin')>管理员</option></select><small>管理员可以管理账号和全部报价。</small></label>
    @unless($editing)
        <label><span>登录密码</span><input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="至少 8 位"></label>
        <label><span>确认密码</span><input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" placeholder="再次输入密码"></label>
    @endunless
</div>
