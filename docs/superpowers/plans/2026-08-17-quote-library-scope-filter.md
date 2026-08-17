# 报价库范围筛选实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在历史报价库中增加“历史报价大厅 / 自用报价”范围筛选，并让范围筛选与现有条件、分页和权限规则协同工作。

**Architecture:** 继续使用现有 GET 查询和 `QuoteFilter` 服务。控制器只白名单接收 `scope`，过滤服务接收当前用户并在数据库查询层添加所有者条件，Blade 用当前查询参数生成范围切换链接；不新增数据库字段或路由。

**Tech Stack:** Laravel 12、PHP、Eloquent、Blade、PHPUnit、现有 `workspace.css`。

---

### Task 1: 为范围查询建立失败测试

**Files:**
- Modify: `tests/Feature/Quotes/QuoteFilterTest.php`

- [ ] **Step 1: 添加自用报价隔离测试**

在现有 `QuoteFilterTest` 中增加测试，创建两个活动员工、一个属于当前用户的 historical 报价、一个属于其他用户的 historical 报价和一个属于当前用户的 draft 报价，然后请求：

```php
public function test_scope_mine_returns_only_the_current_users_historical_quotes(): void
{
    $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
    $other = User::factory()->create(['role' => 'employee', 'is_active' => true]);
    $mine = $this->quote($owner, ['title' => 'Mine']);
    $this->quote($other, ['title' => 'Other']);
    $this->quote($owner, ['title' => 'Draft', 'status' => 'draft']);

    $response = $this->actingAs($owner)->get(route('quotes.index', ['scope' => 'mine']));

    $response->assertOk();
    $response->assertViewHas('quotes', fn ($quotes): bool =>
        $quotes->count() === 1 && $quotes->first()->is($mine)
    );
}
```

- [ ] **Step 2: 添加默认和非法 scope 测试**

增加一个测试，创建两个不同员工的 historical 报价，分别请求无 `scope` 和 `scope=unexpected`，断言两次结果都包含两份报价；这锁定大厅默认行为和非法值回退行为。

- [ ] **Step 3: 为页面范围切换写失败契约测试**

在 `tests/Feature/Views/WorkspaceViewContractTest.php` 增加一个测试，读取 `resources/views/quotes/index.blade.php`，断言包含 `quote-scope-switch`、`历史报价大厅`、`自用报价`、`scope`、`aria-current`；同时读取 `public/css/workspace.css`，断言包含 `.quote-scope-switch` 和 `.quote-scope-option.active`。

- [ ] **Step 4: 运行新增测试确认失败**

运行：

```bash
wsl.exe sh -lc "cd '/mnt/d/旅游内部系统/travel-quote-system' && php artisan test tests/Feature/Quotes/QuoteFilterTest.php tests/Feature/Views/WorkspaceViewContractTest.php --filter='scope|范围|quote_scope'"
```

预期：新增查询测试因 `scope` 尚未生效而失败，视图契约测试因切换标记尚不存在而失败；失败不能来自 PHP 语法错误。

### Task 2: 实现后端范围过滤

**Files:**
- Modify: `app/Services/QuoteFilter.php`
- Modify: `app/Http/Controllers/QuoteController.php`

- [ ] **Step 1: 规范化 scope 并保留现有条件**

在 `QuoteFilter` 增加：

```php
public function history(array $filters, User $viewer): Builder
{
    return $this->apply(
        Quote::query()->historical()->with(['createdBy', 'groups.items'])
            ->when($this->scope($filters['scope'] ?? null) === 'mine',
                fn (Builder $query) => $query->where('created_by', $viewer->id)),
        $filters
    );
}

private function scope(mixed $value): string
{
    return in_array($value, ['all', 'mine'], true) ? $value : 'all';
}
```

引入 `App\Models\User`，不在服务内部读取 `auth()`；这样所有者范围由控制器显式传入。

- [ ] **Step 2: 控制器白名单接收 scope 并传入当前用户**

在 `QuoteController@index` 的 `$request->only([...])` 中加入 `scope`，并将查询调用改为：

```php
'quotes' => $filter->history($filters, $request->user())
    ->latest('updated_at')
    ->paginate(20)
    ->withQueryString(),
```

- [ ] **Step 3: 运行后端新增测试确认通过**

运行同一组过滤测试命令，预期范围隔离、默认回退和既有全部筛选测试全部通过。

### Task 3: 增加报价库范围切换 UI

**Files:**
- Modify: `resources/views/quotes/index.blade.php`
- Modify: `public/css/workspace.css`

- [ ] **Step 1: 在 Blade 规范化当前范围并生成保留条件的链接**

在视图顶部设置：

```php
$scope = in_array($currentFilters['scope'] ?? 'all', ['all', 'mine'], true)
    ? $currentFilters['scope']
    : 'all';
$scopeLabels = ['all' => '历史报价大厅', 'mine' => '自用报价'];
```

在现有筛选表单之前添加：

```blade
<nav class="quote-scope-switch" aria-label="报价范围">
    @foreach($scopeLabels as $value => $label)
        <a class="quote-scope-option @if($scope === $value) active @endif"
           href="{{ route('quotes.index', array_merge($currentFilters, ['scope' => $value])) }}"
           @if($scope === $value) aria-current="page" @endif>
            {{ $label }}
        </a>
    @endforeach
</nav>
```

在现有 GET 表单中加入隐藏字段，确保点击“筛选报价”不会把范围重置：

```blade
<input type="hidden" name="scope" value="{{ $scope }}">
```

清空筛选链接保持不带查询参数，因此回到 `all`。

- [ ] **Step 2: 添加紧凑分段切换样式**

在 `.filter-panel` 前后附近加入：

```css
.quote-scope-switch { margin: 0 0 13px 2px; padding: 4px; display: inline-flex; gap: 3px; border: 1px solid var(--line); border-radius: 7px; background: #fff; }
.quote-scope-option { min-height: 32px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center; border-radius: 5px; color: var(--muted); font-size: 12px; font-weight: 700; transition: color .15s ease, background-color .15s ease, box-shadow .15s ease; }
.quote-scope-option:hover { color: var(--blue); background: #f7faff; }
.quote-scope-option.active { color: var(--blue); background: var(--blue-soft); box-shadow: inset 0 0 0 1px rgba(39,100,216,.08); }
```

在 `@media (max-width: 560px)` 中让切换适应窄屏：

```css
.quote-scope-switch { width: 100%; margin-left: 0; }
.quote-scope-option { flex: 1; }
```

- [ ] **Step 3: 运行视图契约测试确认通过**

运行：

```bash
wsl.exe sh -lc "cd '/mnt/d/旅游内部系统/travel-quote-system' && php artisan test tests/Feature/Views/WorkspaceViewContractTest.php --filter='scope|范围|quote_scope'"
```

预期：范围标记、可访问性状态和响应式样式断言通过。

### Task 4: 完整验证与交付

**Files:**
- No new files; review the changed files and the committed design/plan docs.

- [ ] **Step 1: 检查差异和格式**

运行 `git diff --check` 和 `git status --short`，确认只包含本功能相关的代码、测试、样式和计划文件。

- [ ] **Step 2: 运行完整测试套件**

运行：

```bash
wsl.exe sh -lc "cd '/mnt/d/旅游内部系统/travel-quote-system' && php artisan test"
wsl.exe sh -lc "cd '/mnt/d/旅游内部系统/travel-quote-system' && npm test"
```

预期：PHP 和 JavaScript 测试均以退出码 0 完成。

- [ ] **Step 3: 进行浏览器验证**

打开报价库，验证默认“历史报价大厅”、切换到“自用报价”、叠加目的地筛选、刷新分页链接后范围参数仍保留；检查登录页/报价库无控制台错误。按用户此前要求，不生成或展示截图。

- [ ] **Step 4: 提交并推送**

```bash
git add app/Services/QuoteFilter.php app/Http/Controllers/QuoteController.php resources/views/quotes/index.blade.php public/css/workspace.css tests/Feature/Quotes/QuoteFilterTest.php tests/Feature/Views/WorkspaceViewContractTest.php
git commit -m "feat: add quote library scope filter"
git push origin main
```

