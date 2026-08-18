<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteCopyRequest;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Http\Requests\UpdateQuoteSalesStatusRequest;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Services\QuoteFilter;
use App\Services\QuoteManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function index(Request $request, QuoteFilter $filter): View
    {
        Gate::authorize('viewAny', Quote::class);
        $filters = $request->only([
            'scope', 'year', 'month', 'destination', 'duration', 'people_range', 'budget_min', 'budget_max', 'keyword',
        ]);

        return view('quotes.index', [
            'quotes' => $filter->history($filters, $request->user())->latest('updated_at')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Quote::class);
        $quote = new Quote([
            'year' => now()->year,
            'month' => now()->month,
            'duration_days' => 1,
            'nights' => 0,
            'people_count' => 10,
            'status' => 'historical',
        ]);
        $quote->setRelation('groups', collect([
            [
                'name' => 'DAY 01',
                'type' => 'day',
                'sort_order' => 0,
                'subtotal' => 0,
                'items' => [],
            ],
            [
                'name' => '其他项',
                'type' => 'other',
                'sort_order' => 1,
                'subtotal' => 0,
                'items' => [
                    ['time' => '/', 'name' => '全陪导游', 'unit' => '自驾/无', 'quantity' => 0, 'unit_price' => 0, 'actual_total' => null, 'note' => '', 'is_tax' => false, 'tax_rate' => 0, 'sort_order' => 0],
                    ['time' => '', 'name' => '增值税普通发票', 'unit' => '3%', 'quantity' => 1, 'unit_price' => 0, 'actual_total' => 0, 'note' => '', 'is_tax' => true, 'tax_rate' => 0.03, 'sort_order' => 1],
                    ['time' => '', 'name' => '旅游出行团体意外险', 'unit' => '天/位', 'quantity' => 0, 'unit_price' => 0, 'actual_total' => null, 'note' => '10万保额', 'is_tax' => false, 'tax_rate' => 0, 'sort_order' => 2],
                    ['time' => '', 'name' => '旅行社策划/操作服务费', 'unit' => '无', 'quantity' => 0, 'unit_price' => 0, 'actual_total' => null, 'note' => '', 'is_tax' => false, 'tax_rate' => 0, 'sort_order' => 3],
                    ['time' => '', 'name' => '定制横幅1条', 'unit' => '/', 'quantity' => 0, 'unit_price' => 0, 'actual_total' => null, 'note' => '旅行社赠送', 'is_tax' => false, 'tax_rate' => 0, 'sort_order' => 4],
                ],
            ],
        ]));

        return view('quotes.edit', [
            'quote' => $quote,
            'formAction' => route('quotes.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(StoreQuoteRequest $request, QuoteManager $manager): RedirectResponse
    {
        $quote = $manager->create($request->validated(), $request->user());

        $route = $request->input('after_save') === 'preview' ? 'quotes.preview' : 'quotes.show';

        return redirect()->route($route, $quote)->with('success', '报价已保存。');
    }

    public function show(Quote $quote): View
    {
        Gate::authorize('view', $quote);

        return view('quotes.show', ['quote' => $quote->load(['createdBy', 'groups.items'])]);
    }

    public function edit(Quote $quote): View
    {
        Gate::authorize('update', $quote);

        return view('quotes.edit', [
            'quote' => $quote->load(['createdBy', 'groups.items']),
            'formAction' => route('quotes.update', $quote),
            'formMethod' => 'PUT',
        ]);
    }

    public function copyEdit(Quote $quote): View
    {
        Gate::authorize('copy', $quote);

        return view('quotes.edit', [
            'quote' => $quote->load(['createdBy', 'groups.items']),
            'formAction' => route('quotes.copy.store', $quote),
            'formMethod' => 'POST',
            'isCopy' => true,
        ]);
    }

    public function storeCopy(StoreQuoteCopyRequest $request, Quote $quote, QuoteManager $manager): RedirectResponse
    {
        $copy = $manager->createCopy($quote, $request->validated(), $request->user());
        $route = $request->input('after_save') === 'preview' ? 'quotes.preview' : 'quotes.show';

        return redirect()->route($route, $copy)->with('success', '报价已保存。');
    }

    public function update(UpdateQuoteRequest $request, Quote $quote, QuoteManager $manager): RedirectResponse
    {
        $manager->update($quote, $request->validated());

        $route = $request->input('after_save') === 'preview' ? 'quotes.preview' : 'quotes.show';

        return redirect()->route($route, $quote)->with('success', '报价已更新。');
    }

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

    public function destroy(Request $request, Quote $quote): RedirectResponse
    {
        Gate::authorize('delete', $quote);
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'quote.deleted',
            'subject_type' => Quote::class,
            'subject_id' => $quote->id,
            'changes' => [
                'title' => $quote->title,
                'destination' => $quote->destination,
                'created_by' => $quote->created_by,
            ],
            'ip_address' => $request->ip(),
        ]);
        $quote->delete();

        return redirect()->route('quotes.index')->with('success', '报价已删除。');
    }

    public function preview(Quote $quote): View
    {
        Gate::authorize('view', $quote);

        return view('quotes.preview', ['quote' => $quote->load(['createdBy', 'groups.items'])]);
    }

    public function copy(Request $request, Quote $quote): RedirectResponse
    {
        Gate::authorize('copy', $quote);
        $validated = $request->validate(['mode' => ['nullable', 'in:direct,edit']]);

        if (($validated['mode'] ?? 'direct') === 'edit') {
            return redirect()->route('quotes.copy.edit', $quote);
        }

        return redirect()->route('quotes.preview', $quote);
    }
}
