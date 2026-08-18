<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteFilter;
use App\Services\QuoteSalesDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WonQuoteController extends Controller
{
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
}
