<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('users.index', [
            'users' => User::query()
                ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request): void {
                    $search = $request->string('q')->trim()->toString();
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
                })
                ->when(in_array($request->input('role'), ['admin', 'employee'], true), function ($query) use ($request): void {
                    $query->where('role', $request->input('role'));
                })
                ->when(in_array($request->input('status'), ['active', 'inactive'], true), function ($query) use ($request): void {
                    $query->where('is_active', $request->input('status') === 'active');
                })
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            ...$request->safe()->only(['name', 'username', 'role', 'password']),
            'is_active' => true,
        ]);

        $this->audit($request, $user, 'user.created', [
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'is_active' => true,
        ]);

        return redirect('/users')->with('success', '员工账号已创建。');
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $before = $user->only(['name', 'username', 'role']);
        $user->update($request->validated());

        $this->audit($request, $user, 'user.updated', [
            'before' => $before,
            'after' => $user->only(['name', 'username', 'role']),
        ]);

        return redirect('/users')->with('success', '员工资料已更新。');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $isActive = $request->boolean('is_active');

        if ($request->user()->is($user) && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => '不能停用当前登录的管理员账号。',
            ]);
        }

        $before = $user->is_active;
        $user->forceFill([
            'is_active' => $isActive,
            'remember_token' => $isActive ? $user->getRememberToken() : null,
        ])->save();

        if (! $isActive) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $this->audit($request, $user, 'user.status_changed', [
            'before' => $before,
            'after' => $isActive,
        ]);

        return redirect('/users')->with('success', $isActive ? '账号已启用。' : '账号已停用。');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'remember_token' => null,
        ])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->audit($request, $user, 'user.password_reset');

        return redirect('/users')->with('success', '密码已重置。');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    /** @param array<string, mixed>|null $changes */
    private function audit(Request $request, User $subject, string $action, ?array $changes = null): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'changes' => $changes,
            'ip_address' => $request->ip(),
        ]);
    }
}
