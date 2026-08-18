# Quote Sales Status and Won Quote Library Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a default sales follow-up status to every historical quote, expose owner and status controls in quote views, and add a filterable won-quote library with monthly company sales totals.

**Architecture:** Keep the existing `quotes.status` technical lifecycle unchanged and add `sales_status` plus `won_at` for the business lifecycle. Put state-transition rules in `QuoteManager`, filtering in `QuoteFilter`, monthly aggregation in a focused `QuoteSalesDashboard` service, and authorization in the existing `QuotePolicy`. Reuse compact Blade components for the status control while keeping the history and won lists optimized for their distinct columns.

**Tech Stack:** Laravel 12, PHP 8.2+, Eloquent, Blade, MySQL production, SQLite tests, PHPUnit, plain CSS and JavaScript.

---

## File Map

- Create `database/migrations/2026_08_18_000100_add_sales_tracking_to_quotes_table.php`: add indexed business-status and won-time columns.
- Modify `app/Models/Quote.php`: define status constants, labels, casts, accessors, and won scope.
- Modify `app/Services/QuoteManager.php`: perform sales-status transitions and maintain `won_at`.
- Create `app/Http/Requests/UpdateQuoteSalesStatusRequest.php`: authorize and validate status changes.
- Modify `app/Http/Controllers/QuoteController.php`: expose the protected status-update endpoint and audit changes.
- Modify `routes/quotes.php`: register the status route.
- Modify `app/Services/QuoteFilter.php`: build the won-quote list using the existing trip filters.
- Create `app/Services/QuoteSalesDashboard.php`: normalize report month and calculate issued, won, and revenue totals.
- Create `app/Http/Controllers/WonQuoteController.php`: assemble the won page data.
- Modify `routes/quotes.php`: register the won-library route before the quote parameter route.
- Create `resources/views/quotes/_sales_status.blade.php`: shared editable/read-only status control.
- Modify `resources/views/quotes/index.blade.php`: place owner first and add status.
- Modify `resources/views/quotes/show.blade.php`: display the shared status control.
- Create `resources/views/quotes/won.blade.php`: monthly metrics, creator filter, trip filters, and won list.
- Modify `resources/views/layouts/app.blade.php`: add a left-navigation entry with correct active states.
- Modify `resources/views/components/icon.blade.php`: add a won/check icon if the existing set has no suitable icon.
- Modify `public/css/workspace.css`: compact status controls, 11-column history table, metrics, and won table.
- Create `tests/Feature/Quotes/QuoteSalesStatusTest.php`: transition, validation, authorization, and audit coverage.
- Create `tests/Feature/Quotes/WonQuoteDashboardTest.php`: month, creator, aggregation, and list-filter coverage.
- Modify `tests/Feature/Quotes/QuoteFilterTest.php`: history owner/status rendering contract.
- Modify `tests/Feature/Views/WorkspaceViewContractTest.php`: navigation and new-view markup contracts.

### Task 1: Persist the Business Sales State

**Files:**
- Create: `database/migrations/2026_08_18_000100_add_sales_tracking_to_quotes_table.php`
- Modify: `app/Models/Quote.php`
- Test: `tests/Feature/Quotes/QuoteSalesStatusTest.php`

- [ ] **Step 1: Write the failing default-state test**

Create the test class with database refresh and assert that a quote created without explicit sales fields uses the database default:

```php
<?php

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteSalesStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotes_default_to_following_without_a_won_time(): void
    {
        $owner = User::factory()->create();
        $quote = Quote::query()->create($this->quoteAttributes($owner));

        $this->assertSame(Quote::SALES_FOLLOWING, $quote->fresh()->sales_status);
        $this->assertNull($quote->fresh()->won_at);
        $this->assertSame('跟进中', $quote->fresh()->sales_status_label);
    }

    /** @return array<string, mixed> */
    private function quoteAttributes(User $owner): array
    {
        return [
            'created_by' => $owner->id,
            'title' => '惠州团建报价',
            'destination' => '惠州',
            'year' => 2026,
            'month' => 8,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 20,
            'total_amount' => 17060.92,
            'per_person_amount' => 853.05,
            'status' => 'historical',
        ];
    }
}
```

- [ ] **Step 2: Run the test and verify the schema failure**

Run:

```powershell
php artisan test tests/Feature/Quotes/QuoteSalesStatusTest.php --filter=default
```

Expected: FAIL because `sales_status`, constants, and label accessors do not exist.

- [ ] **Step 3: Add the migration**

Create the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->string('sales_status', 20)->default('following')->index()->after('status');
            $table->timestamp('won_at')->nullable()->index()->after('sales_status');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex(['sales_status']);
            $table->dropIndex(['won_at']);
            $table->dropColumn(['sales_status', 'won_at']);
        });
    }
};
```

- [ ] **Step 4: Add model constants, casts, fillable fields, label accessor, and won scope**

Add to `Quote`:

```php
public const SALES_FOLLOWING = 'following';
public const SALES_OTHER = 'other';
public const SALES_WON = 'won';

public const SALES_STATUS_LABELS = [
    self::SALES_FOLLOWING => '跟进中',
    self::SALES_OTHER => '其他',
    self::SALES_WON => '已成交',
];
```

Add `sales_status` and `won_at` to `$fillable`, add `'won_at' => 'datetime'` to `casts()`, then add:

```php
public function scopeWon(Builder $query): Builder
{
    return $query->where('sales_status', self::SALES_WON)->whereNotNull('won_at');
}

protected function salesStatusLabel(): Attribute
{
    return Attribute::get(fn (): string => self::SALES_STATUS_LABELS[$this->sales_status] ?? '跟进中');
}
```

- [ ] **Step 5: Run the focused test**

Run:

```powershell
php artisan test tests/Feature/Quotes/QuoteSalesStatusTest.php --filter=default
```

Expected: PASS.

- [ ] **Step 6: Commit the schema and model state**

```powershell
git add database/migrations/2026_08_18_000100_add_sales_tracking_to_quotes_table.php app/Models/Quote.php tests/Feature/Quotes/QuoteSalesStatusTest.php
git commit -m "feat: add quote sales tracking state"
```

### Task 2: Implement Authorized Status Transitions

**Files:**
- Modify: `app/Services/QuoteManager.php`
- Create: `app/Http/Requests/UpdateQuoteSalesStatusRequest.php`
- Modify: `app/Http/Controllers/QuoteController.php`
- Modify: `routes/quotes.php`
- Test: `tests/Feature/Quotes/QuoteSalesStatusTest.php`

- [ ] **Step 1: Add failing transition, idempotency, validation, permission, and audit tests**

Add tests that freeze time with `Carbon::setTestNow('2026-08-18 10:30:00')` and verify:

```php
public function test_owner_can_mark_a_quote_won_and_reverting_clears_won_time(): void
{
    $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
    $quote = Quote::query()->create($this->quoteAttributes($owner));
    $wonAt = now()->setDateTime(2026, 8, 18, 10, 30);
    \Illuminate\Support\Carbon::setTestNow($wonAt);

    $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
        'sales_status' => Quote::SALES_WON,
    ])->assertRedirect();

    $quote->refresh();
    $this->assertSame(Quote::SALES_WON, $quote->sales_status);
    $this->assertTrue($quote->won_at->equalTo($wonAt));

    \Illuminate\Support\Carbon::setTestNow($wonAt->copy()->addDay());
    $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
        'sales_status' => Quote::SALES_WON,
    ])->assertRedirect();
    $this->assertTrue($quote->fresh()->won_at->equalTo($wonAt));

    $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
        'sales_status' => Quote::SALES_OTHER,
    ])->assertRedirect();
    $this->assertNull($quote->fresh()->won_at);
}

public function test_employee_cannot_change_another_users_status_but_admin_can(): void
{
    $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
    $other = User::factory()->create(['role' => 'employee', 'is_active' => true]);
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $quote = Quote::query()->create($this->quoteAttributes($owner));

    $this->actingAs($other)->patch(route('quotes.sales-status', $quote), [
        'sales_status' => Quote::SALES_WON,
    ])->assertForbidden();

    $this->actingAs($admin)->patch(route('quotes.sales-status', $quote), [
        'sales_status' => Quote::SALES_WON,
    ])->assertRedirect();

    $this->assertSame(Quote::SALES_WON, $quote->fresh()->sales_status);
}

public function test_sales_status_rejects_unknown_values_and_audits_real_changes(): void
{
    $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
    $quote = Quote::query()->create($this->quoteAttributes($owner));

    $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
        'sales_status' => 'cancelled',
    ])->assertSessionHasErrors('sales_status');

    $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
        'sales_status' => Quote::SALES_WON,
    ])->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $owner->id,
        'action' => 'quote.sales_status_changed',
        'subject_id' => $quote->id,
    ]);
}
```

- [ ] **Step 2: Run the transition tests and verify they fail**

Run:

```powershell
php artisan test tests/Feature/Quotes/QuoteSalesStatusTest.php
```

Expected: FAIL because the route, request, and transition method do not exist.

- [ ] **Step 3: Add the request validation and authorization**

Create `UpdateQuoteSalesStatusRequest`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateQuoteSalesStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('quote'));
    }

    public function rules(): array
    {
        return [
            'sales_status' => ['required', Rule::in(array_keys(Quote::SALES_STATUS_LABELS))],
        ];
    }
}
```

- [ ] **Step 4: Add the transaction-safe transition method**

Add to `QuoteManager`:

```php
public function updateSalesStatus(Quote $quote, string $salesStatus): Quote
{
    return DB::transaction(function () use ($quote, $salesStatus): Quote {
        if ($quote->sales_status === $salesStatus) {
            return $quote;
        }

        $quote->sales_status = $salesStatus;
        $quote->won_at = $salesStatus === Quote::SALES_WON ? now() : null;
        $quote->save();

        return $quote->refresh();
    });
}
```

- [ ] **Step 5: Add the controller action and route**

Import `UpdateQuoteSalesStatusRequest` and add:

```php
public function updateSalesStatus(
    UpdateQuoteSalesStatusRequest $request,
    Quote $quote,
    QuoteManager $manager
): RedirectResponse {
    $oldStatus = $quote->sales_status;
    $oldWonAt = $quote->won_at?->toISOString();
    $manager->updateSalesStatus($quote, $request->validated('sales_status'));

    if ($oldStatus !== $quote->sales_status) {
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'quote.sales_status_changed',
            'subject_type' => Quote::class,
            'subject_id' => $quote->id,
            'changes' => [
                'sales_status' => ['old' => $oldStatus, 'new' => $quote->sales_status],
                'won_at' => ['old' => $oldWonAt, 'new' => $quote->won_at?->toISOString()],
            ],
            'ip_address' => $request->ip(),
        ]);
    }

    return back()->with('success', '跟进状态已更新。');
}
```

Register before `GET /quotes/{quote}`:

```php
Route::patch('/quotes/{quote}/sales-status', [QuoteController::class, 'updateSalesStatus'])
    ->name('quotes.sales-status');
```

- [ ] **Step 6: Run the status test file**

Run:

```powershell
php artisan test tests/Feature/Quotes/QuoteSalesStatusTest.php
```

Expected: all tests in the file PASS.

- [ ] **Step 7: Commit the protected transition endpoint**

```powershell
git add app/Services/QuoteManager.php app/Http/Requests/UpdateQuoteSalesStatusRequest.php app/Http/Controllers/QuoteController.php routes/quotes.php tests/Feature/Quotes/QuoteSalesStatusTest.php
git commit -m "feat: add protected quote sales status updates"
```

### Task 3: Build Monthly Won-Quote Queries and Totals

**Files:**
- Modify: `app/Services/QuoteFilter.php`
- Create: `app/Services/QuoteSalesDashboard.php`
- Test: `tests/Feature/Quotes/WonQuoteDashboardTest.php`

- [ ] **Step 1: Write failing dashboard tests**

Create fixtures across July and August and verify the report uses creation time for issued quotes, `won_at` for won totals, excludes drafts, supports creator filtering, and preserves trip filters. Test the service and query builder directly:

```php
$period = app(QuoteSalesDashboard::class)->period('2026-08');
$summary = app(QuoteSalesDashboard::class)->summary($period, null);

$this->assertSame([
    'issued_count' => 3,
    'won_count' => 2,
    'won_amount' => 42000.0,
], $summary);

$filtered = app(QuoteFilter::class)->won([
    'creator_id' => $owner->id,
    'destination' => '惠州',
], $period['start'], $period['end'])->get();

$this->assertCount(1, $filtered);
$this->assertSame('八月成交报价', $filtered->first()->title);
```

Add an invalid-month test asserting `period('2026-13')['value']` equals the current month.

- [ ] **Step 2: Run the dashboard tests and verify missing-class failures**

Run:

```powershell
php artisan test tests/Feature/Quotes/WonQuoteDashboardTest.php
```

Expected: FAIL because the sales dashboard service and won filter do not exist.

- [ ] **Step 3: Implement the report-period and aggregate service**

Create `QuoteSalesDashboard` with these public methods:

```php
public function period(?string $reportMonth): array
{
    if (is_string($reportMonth) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportMonth) === 1) {
        $start = CarbonImmutable::createFromFormat('!Y-m', $reportMonth, config('app.timezone'));
    } else {
        $start = CarbonImmutable::now()->startOfMonth();
    }

    return [
        'value' => $start->format('Y-m'),
        'start' => $start->startOfDay(),
        'end' => $start->endOfMonth()->endOfDay(),
    ];
}

public function summary(array $period, ?int $creatorId = null): array
{
    $issued = Quote::query()->historical()
        ->when($creatorId, fn (Builder $query, int $id) => $query->where('created_by', $id))
        ->whereBetween('created_at', [$period['start'], $period['end']])
        ->count();

    $won = Quote::query()->historical()->won()
        ->when($creatorId, fn (Builder $query, int $id) => $query->where('created_by', $id))
        ->whereBetween('won_at', [$period['start'], $period['end']]);

    return [
        'issued_count' => $issued,
        'won_count' => (clone $won)->count(),
        'won_amount' => (float) (clone $won)->sum('total_amount'),
    ];
}
```

Use `CarbonImmutable`, `Builder`, and `Quote` imports and document the returned array shapes.

- [ ] **Step 4: Extend `QuoteFilter` for the won list**

Add:

```php
public function won(array $filters, CarbonImmutable $start, CarbonImmutable $end): Builder
{
    return $this->apply(
        Quote::query()->historical()->won()
            ->with(['createdBy', 'groups.items'])
            ->whereBetween('won_at', [$start, $end])
            ->when($this->integer($filters['creator_id'] ?? null),
                fn (Builder $query, int $creatorId) => $query->where('created_by', $creatorId)),
        $filters
    );
}
```

Import `Carbon\CarbonImmutable`.

- [ ] **Step 5: Run dashboard and existing filter tests**

Run:

```powershell
php artisan test tests/Feature/Quotes/WonQuoteDashboardTest.php tests/Feature/Quotes/QuoteFilterTest.php
```

Expected: all direct period, aggregate, creator-filter, and trip-filter assertions PASS without requiring a Blade view.

- [ ] **Step 6: Commit dashboard query behavior**

```powershell
git add app/Services/QuoteFilter.php app/Services/QuoteSalesDashboard.php tests/Feature/Quotes/WonQuoteDashboardTest.php
git commit -m "feat: add monthly won quote dashboard queries"
```

### Task 4: Add Compact Status Controls and the Won Library UI

**Files:**
- Create: `app/Http/Controllers/WonQuoteController.php`
- Modify: `routes/quotes.php`
- Create: `resources/views/quotes/_sales_status.blade.php`
- Modify: `resources/views/quotes/index.blade.php`
- Modify: `resources/views/quotes/show.blade.php`
- Create: `resources/views/quotes/won.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/components/icon.blade.php`
- Modify: `public/css/workspace.css`
- Modify: `tests/Feature/Quotes/QuoteFilterTest.php`
- Modify: `tests/Feature/Views/WorkspaceViewContractTest.php`

- [ ] **Step 1: Write failing rendered-view and source-contract tests**

Add assertions for:

```php
$response->assertSeeInOrder(['报价人', '报价名称']);
$response->assertSee($owner->name);
$response->assertSee('跟进中');
$response->assertSee('name="sales_status"', false);
$response->assertSee('action="'.route('quotes.sales-status', $quote).'"', false);
```

For a non-owner employee, assert the label is visible and the status form action is absent. In `WorkspaceViewContractTest`, assert the layout includes “已成交报价”, the won view exists, and the won view contains `report_month`, `creator_id`, metric classes, “成交日期”, and all existing trip filter names.

- [ ] **Step 2: Run the focused UI tests and verify failure**

Run:

```powershell
php artisan test tests/Feature/Quotes/QuoteFilterTest.php tests/Feature/Views/WorkspaceViewContractTest.php
```

Expected: FAIL because owner/status columns, navigation, partial, and won view are absent.

- [ ] **Step 3: Add the focused won-page controller and route**

Create `WonQuoteController@index` that authorizes `viewAny`, collects the shared filters, normalizes the report period, paginates the won query, and passes active creators:

```php
public function index(Request $request, QuoteFilter $filter, QuoteSalesDashboard $dashboard): View
{
    Gate::authorize('viewAny', Quote::class);
    $filters = $request->only([
        'report_month', 'creator_id', 'year', 'month', 'destination', 'duration',
        'people_range', 'budget_min', 'budget_max', 'keyword',
    ]);
    $period = $dashboard->period($filters['report_month'] ?? null);
    $creatorId = filter_var($filters['creator_id'] ?? null, FILTER_VALIDATE_INT);
    $creatorId = $creatorId === false ? null : (int) $creatorId;

    return view('quotes.won', [
        'quotes' => $filter->won($filters, $period['start'], $period['end'])
            ->latest('won_at')->paginate(20)->withQueryString(),
        'summary' => $dashboard->summary($period, $creatorId),
        'period' => $period,
        'filters' => $filters,
        'creators' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username']),
    ]);
}
```

Register `GET /won-quotes` as `quotes.won` before quote parameter routes.

- [ ] **Step 4: Create the reusable status control**

Create a compact partial that uses a select form for authorized users and a read-only badge otherwise:

```blade
@can('update', $quote)
    <form class="sales-status-form" method="POST" action="{{ route('quotes.sales-status', $quote) }}">
        @csrf
        @method('PATCH')
        <label class="sales-status-select {{ $quote->sales_status }}">
            <span class="sr-only">跟进状态</span>
            <select name="sales_status" aria-label="修改跟进状态" onchange="this.form.requestSubmit()">
                @foreach(\App\Models\Quote::SALES_STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected($quote->sales_status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>
@else
    <span class="sales-status-badge {{ $quote->sales_status }}">{{ $quote->sales_status_label }}</span>
@endcan
```

- [ ] **Step 5: Update the history list and detail view**

In `quotes/index.blade.php`, put `<th>报价人</th>` before the title column, render `{{ $quote->createdBy?->name ?? $quote->createdBy?->username ?? '未知账号' }}`, add “跟进状态” before actions, and include the status partial. Update the empty-state colspan from 9 to 11.

In `quotes/show.blade.php`, extend the meta strip with:

```blade
<div class="detail-sales-status">
    <span>跟进状态</span>
    @include('quotes._sales_status', ['quote' => $quote])
</div>
```

- [ ] **Step 6: Add the won-library page**

Create `quotes/won.blade.php` with:

- a page toolbar titled “已成交报价”;
- a report bar with `<input type="month" name="report_month">` defaulting to `$period['value']`;
- creator selector populated from `$creators`;
- three metric blocks using `summary-metric` classes;
- the existing year/month/destination/duration/people/budget/keyword filter names;
- a table whose first column is “报价人” and whose final informational column is “成交日期”;
- existing eye/copy/edit/delete icon actions and pagination with query-string preservation;
- an empty state that says “该月份没有匹配的已成交报价”.

Use a single GET form so report month, creator, and trip filters always move together. Format amount with `number_format($summary['won_amount'], 2)` and won time with `$quote->won_at?->format('Y-m-d')`.

- [ ] **Step 7: Add navigation and compact layout styles**

Add a second navigation item before user management. History is active only for `quotes.index`, create, show, edit, preview, and copy routes; won is active for `quotes.won`.

Add CSS for:

```css
.sales-status-form { margin: 0; }
.sales-status-select,
.sales-status-badge { display: inline-flex; align-items: center; min-width: 72px; min-height: 28px; border: 1px solid var(--line); border-radius: 6px; font-size: 12px; font-weight: 700; }
.sales-status-select select { width: 100%; border: 0; background: transparent; color: inherit; font: inherit; padding: 4px 22px 4px 8px; cursor: pointer; }
.sales-status-select.following, .sales-status-badge.following { color: #9a5b00; background: #fff7df; border-color: #efd89c; }
.sales-status-select.other, .sales-status-badge.other { color: #4b5563; background: #f3f4f6; border-color: #d1d5db; }
.sales-status-select.won, .sales-status-badge.won { color: #166534; background: #eaf7ee; border-color: #b8dfc2; }
.sales-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.summary-metric { padding: 14px 16px; border: 1px solid var(--line); background: #fff; border-radius: 6px; }
.summary-metric span { display: block; color: var(--muted); font-size: 12px; }
.summary-metric strong { display: block; margin-top: 4px; font-size: 22px; color: var(--ink); }
```

Adjust `.quote-library-table` column widths so owner, status, and actions remain fixed while title and highlights use `overflow: hidden; text-overflow: ellipsis; white-space: nowrap`. Do not introduce horizontal scrolling for the library tables. Add a mobile breakpoint that stacks metrics and filters without overlapping text.

- [ ] **Step 8: Run UI and dashboard tests**

Run:

```powershell
php artisan test tests/Feature/Quotes/QuoteFilterTest.php tests/Feature/Quotes/WonQuoteDashboardTest.php tests/Feature/Views/WorkspaceViewContractTest.php tests/Feature/Views/QuoteWorkflowUiTest.php
```

Expected: all focused tests PASS.

- [ ] **Step 9: Commit the complete UI**

```powershell
git add app/Http/Controllers/WonQuoteController.php routes/quotes.php resources/views/quotes/_sales_status.blade.php resources/views/quotes/index.blade.php resources/views/quotes/show.blade.php resources/views/quotes/won.blade.php resources/views/layouts/app.blade.php resources/views/components/icon.blade.php public/css/workspace.css tests/Feature/Quotes/QuoteFilterTest.php tests/Feature/Views/WorkspaceViewContractTest.php
git commit -m "feat: add won quote library interface"
```

### Task 5: Local Regression and Browser Verification

**Files:**
- Modify only files implicated by failures found in this task.

- [ ] **Step 1: Run code formatting**

Run:

```powershell
vendor\bin\pint
```

Expected: formatting completes without errors. Review changes and keep only files in this feature scope.

- [ ] **Step 2: Run the full PHP suite**

Run:

```powershell
php artisan test
```

Expected: all existing and new tests PASS.

- [ ] **Step 3: Run JavaScript tests**

Run:

```powershell
npm test
```

Expected: all JavaScript tests PASS.

- [ ] **Step 4: Rebuild a clean local database and start the app**

Run:

```powershell
php artisan migrate:fresh --seed --env=testing
php artisan serve --host=127.0.0.1 --port=5138
```

Expected: migrations and seeders complete, and the app serves at `http://127.0.0.1:5138`.

- [ ] **Step 5: Verify the workflow in a local browser**

Check desktop and narrow viewports:

1. History list first column shows account name.
2. New quotes display “跟进中”.
3. Owner/admin can change status; another employee sees a read-only label.
4. Changing to “已成交” adds the quote to the selected month in “已成交报价”.
5. Changing away from “已成交” removes it from that page.
6. Month and creator filters update all three metrics and the won list.
7. History and won tables stay single-line without page-level horizontal overflow.
8. Existing view, copy, edit, delete, preview, image, and spreadsheet actions remain reachable.

- [ ] **Step 6: Review diffs and commit verification fixes**

```powershell
git diff --check
git status --short
git add app database resources public tests routes
git commit -m "test: verify quote sales workflow"
```

If verification required no code changes, do not create an empty commit.

### Task 6: Push and Deploy After Local Approval

**Files:**
- No source changes expected; deployment uses the committed repository state.

- [ ] **Step 1: Confirm branch state and push**

Run:

```powershell
git status --short
git log -5 --oneline
git push origin main
```

Expected: worktree clean and `main` pushed successfully to GitHub.

- [ ] **Step 2: Back up the production application and database through the existing Baota workflow**

Confirm the backup exists before upload. Do not display panel or database credentials in logs or responses.

- [ ] **Step 3: Upload the committed changed files and run the migration**

Run production Laravel commands from the deployed application directory:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Expected: the new migration reports success and Laravel caches rebuild without errors.

- [ ] **Step 4: Run production smoke checks**

Verify:

1. Login returns 200.
2. `/quotes` renders owner and status columns.
3. `/won-quotes` renders metrics and filters without 5xx.
4. An authorized test quote can move to won and back while totals update.
5. Existing quote detail, copy, edit, preview, image, and spreadsheet actions still load.

- [ ] **Step 5: Report deployment outcome**

Report the Git commit, local PHP and JavaScript test counts, migration result, production URLs checked, and any residual risk. Never repeat credentials.
