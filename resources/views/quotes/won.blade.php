@extends('layouts.app')

@section('title', '已成交报价 · 旅游报价工作台')

@section('content')
@php
    $quoteRows = $quotes ?? collect();
    $currentFilters = $filters ?? request()->all();
    $summary = $summary ?? ['issued_count' => 0, 'won_count' => 0, 'won_amount' => 0];
    $reportMonth = $period['value'] ?? ($currentFilters['report_month'] ?? now()->format('Y-m'));
@endphp
<section class="page-toolbar">
    <div>
        <h1>已成交报价</h1>
        <p>客户已认可的高可信度方案，可按报价人和行程条件继续筛选。</p>
    </div>
    @if(Route::has('quotes.create'))
        <a class="btn primary" href="{{ route('quotes.create') }}"><x-icon name="plus" />新增历史报价</a>
    @endif
</section>

<section class="sales-summary" aria-label="成交统计">
    <div class="summary-metric"><span>本月出报价 · {{ substr($reportMonth, 0, 4) }}年{{ (int) substr($reportMonth, 5, 2) }}月</span><strong>{{ number_format((int) $summary['issued_count']) }} 份</strong></div>
    <div class="summary-metric"><span>本月成交 · {{ substr($reportMonth, 0, 4) }}年{{ (int) substr($reportMonth, 5, 2) }}月</span><strong>{{ number_format((int) $summary['won_count']) }} 份</strong></div>
    <div class="summary-metric"><span>成交额</span><strong>¥{{ number_format((float) $summary['won_amount'], 2) }}</strong></div>
</section>

<section class="panel filter-panel">
    <form class="quote-filters won-filters" method="GET" action="{{ route('quotes.won') }}">
        <label><span>统计月份</span><input type="month" name="report_month" value="{{ $reportMonth }}"></label>
        <label><span>报价人</span><select name="creator_id"><option value="">全部报价人</option>@foreach($creators as $creator)<option value="{{ $creator->id }}" @selected(($currentFilters['creator_id'] ?? '') == $creator->id)>{{ $creator->name ?: $creator->username }}</option>@endforeach</select></label>
        <label><span>行程年</span><select name="year"><option value="">全部年份</option>@foreach(range((int) date('Y') + 1, 2020) as $year)<option value="{{ $year }}" @selected(($currentFilters['year'] ?? '') == $year)>{{ $year }}年</option>@endforeach</select></label>
        <label><span>行程月</span><select name="month"><option value="">全部月份</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected(($currentFilters['month'] ?? '') == $month)>{{ $month }}月</option>@endforeach</select></label>
        <label><span>目的地</span><input name="destination" value="{{ $currentFilters['destination'] ?? '' }}" placeholder="如：惠州"></label>
        <label><span>行程类型</span><select name="duration"><option value="">全部类型</option>@foreach([1=>'一日游',2=>'两天一夜',3=>'三天两夜',4=>'四天三夜'] as $days=>$label)<option value="{{ $days }}" @selected(($currentFilters['duration'] ?? '') == $days)>{{ $label }}</option>@endforeach</select></label>
        <label><span>人数</span><select name="people_range"><option value="">全部人数</option>@foreach(['10-20','21-30','31-40','41-50','51-60','61-70','71-80','81-90','91-100','100-150','151-200'] as $range)<option value="{{ $range }}" @selected(($currentFilters['people_range'] ?? '') === $range)>{{ $range }}人</option>@endforeach</select></label>
        <label><span>人均预算</span><div class="range-inputs"><input type="number" name="budget_min" min="0" value="{{ $currentFilters['budget_min'] ?? '' }}" placeholder="最低"><i>-</i><input type="number" name="budget_max" min="0" value="{{ $currentFilters['budget_max'] ?? '' }}" placeholder="最高"></div></label>
        <label class="keyword-field"><span>关键词</span><input name="keyword" value="{{ $currentFilters['keyword'] ?? '' }}" placeholder="项目、住宿、客户或来源"></label>
        <div class="filter-actions"><button class="icon-btn primary" type="submit" data-tooltip="筛选成交报价" aria-label="筛选成交报价"><x-icon name="search" /></button><a class="icon-btn" href="{{ route('quotes.won') }}" data-tooltip="清空筛选" aria-label="清空筛选"><x-icon name="reset" /></a></div>
    </form>
</section>

<section class="panel table-panel">
    <header class="panel-head"><div><h2>成交报价列表</h2><p>只显示所选统计月份内已成交的历史报价。</p></div><span class="record-count">共 {{ method_exists($quoteRows, 'total') ? $quoteRows->total() : count($quoteRows) }} 份</span></header>
    <div class="data-table-wrap">
        <table class="data-table quote-library-table won-quotes-table">
            <thead><tr><th>报价人</th><th>报价名称</th><th>年月</th><th>目的地</th><th>行程</th><th>人数</th><th>人均</th><th>成交额</th><th>主要项目</th><th>成交日期</th><th class="actions-cell">操作</th></tr></thead>
            <tbody>
            @forelse($quoteRows as $quote)
                @php $people = (int) ($quote->people_count ?? 0); $total = (float) ($quote->total_amount ?? 0); $summaryText = $quote->highlights ?? ''; @endphp
                <tr>
                    <td class="owner-cell">{{ $quote->createdBy?->name ?? $quote->createdBy?->username ?? '未知账号' }}</td>
                    <td><span class="table-title" title="{{ $quote->title ?? $quote->customer_title ?? '未命名报价' }}">{{ $quote->title ?? $quote->customer_title ?? '未命名报价' }}</span></td>
                    <td>{{ $quote->year ?? '-' }}.{{ str_pad((string) ($quote->month ?? '-'), 2, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $quote->destination ?? '-' }}</td>
                    <td>{{ $quote->trip_type }}</td>
                    <td>{{ $people }}人</td>
                    <td class="money">¥{{ number_format((float) ($quote->per_person_amount ?? ($people > 0 ? $total / $people : 0)), 0) }}</td>
                    <td class="money strong">¥{{ number_format($total, 2) }}</td>
                    <td class="summary-cell" title="{{ $summaryText }}">{{ $summaryText ?: '查看详情了解行程项目' }}</td>
                    <td>{{ $quote->won_at?->format('Y-m-d') ?? '-' }}</td>
                    <td class="actions-cell"><div class="row-actions"><a class="icon-btn" href="{{ route('quotes.show', $quote) }}" data-tooltip="查看报价详情" aria-label="查看报价详情"><x-icon name="eye" /></a><a class="icon-btn" href="{{ route('quotes.preview', $quote) }}" data-tooltip="直接使用报价" aria-label="直接使用报价"><x-icon name="copy" /></a><a class="icon-btn primary" href="{{ route('quotes.copy.edit', $quote) }}" data-tooltip="复制并微调" aria-label="复制并微调"><x-icon name="edit" /></a>@can('delete', $quote)<form method="POST" action="{{ route('quotes.destroy', $quote) }}" data-confirm="确定删除这份原始报价吗？删除后无法恢复。">@csrf @method('DELETE')<button class="icon-btn danger" type="submit" data-tooltip="删除原始报价" aria-label="删除原始报价"><x-icon name="trash" /></button></form>@endcan</div></td>
                </tr>
            @empty
                <tr><td colspan="11"><div class="empty-state"><x-icon name="search" /><strong>该月份没有匹配的已成交报价</strong><span>调整统计月份或筛选条件后再试。</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($quoteRows, 'links'))<div class="pagination-wrap">{{ $quoteRows->withQueryString()->links() }}</div>@endif
</section>
@endsection
