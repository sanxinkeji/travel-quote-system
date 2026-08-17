@extends('layouts.app')

@section('title', '历史报价库 · 旅游报价工作台')

@section('content')
@php
    $quoteRows = $quotes ?? collect();
    $currentFilters = $filters ?? request()->all();
    $scope = $currentFilters['scope'] ?? 'all';
    $scope = in_array($scope, ['all', 'mine'], true) ? $scope : 'all';
    $scopeLabels = ['all' => '历史报价大厅', 'mine' => '自用报价'];
@endphp
<section class="page-toolbar">
    <div>
        <h1>历史报价库</h1>
        <p>按客户需求筛选方案，找到最接近的一份后直接复制或进入微调。</p>
    </div>
    @if(Route::has('quotes.create'))
        <a class="btn primary" href="{{ route('quotes.create') }}"><x-icon name="plus" />新增历史报价</a>
    @endif
</section>

<nav class="quote-scope-switch" aria-label="报价范围">
    @foreach($scopeLabels as $value => $label)
        <a class="quote-scope-option @if($scope === $value) active @endif"
           href="{{ route('quotes.index', array_merge($currentFilters, ['scope' => $value])) }}"
           @if($scope === $value) aria-current="page" @endif>
            {{ $label }}
        </a>
    @endforeach
</nav>

<section class="panel filter-panel">
    <form class="quote-filters" method="GET" action="{{ Route::has('quotes.index') ? route('quotes.index') : url('/quotes') }}">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <label><span>年份</span><select name="year"><option value="">全部年份</option>@foreach(range((int) date('Y') + 1, 2020) as $year)<option value="{{ $year }}" @selected(($currentFilters['year'] ?? '') == $year)>{{ $year }}年</option>@endforeach</select></label>
        <label><span>月份</span><select name="month"><option value="">全部月份</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected(($currentFilters['month'] ?? '') == $month)>{{ $month }}月</option>@endforeach</select></label>
        <label><span>目的地</span><input name="destination" value="{{ $currentFilters['destination'] ?? '' }}" placeholder="如：惠州"></label>
        <label><span>行程类型</span><select name="duration"><option value="">全部类型</option>@foreach([1=>'一日游',2=>'两天一夜',3=>'三天两夜',4=>'四天三夜'] as $days=>$label)<option value="{{ $days }}" @selected(($currentFilters['duration'] ?? $currentFilters['duration_days'] ?? '') == $days)>{{ $label }}</option>@endforeach</select></label>
        <label><span>人数</span><select name="people_range"><option value="">全部人数</option>@foreach(['10-20','21-30','31-40','41-50','51-60','61-70','71-80','81-90','91-100','100-150','151-200'] as $range)<option value="{{ $range }}" @selected(($currentFilters['people_range'] ?? '') === $range)>{{ $range }}人</option>@endforeach</select></label>
        <label><span>人均预算</span><div class="range-inputs"><input type="number" name="budget_min" min="0" value="{{ $currentFilters['budget_min'] ?? '' }}" placeholder="最低"><i>-</i><input type="number" name="budget_max" min="0" value="{{ $currentFilters['budget_max'] ?? '' }}" placeholder="最高"></div></label>
        <label class="keyword-field"><span>关键词</span><input name="keyword" value="{{ $currentFilters['keyword'] ?? '' }}" placeholder="项目、住宿、客户或来源"></label>
        <div class="filter-actions">
            <button class="icon-btn primary" type="submit" data-tooltip="筛选报价" aria-label="筛选报价"><x-icon name="search" /></button>
            <a class="icon-btn" href="{{ Route::has('quotes.index') ? route('quotes.index') : url('/quotes') }}" data-tooltip="清空筛选" aria-label="清空筛选"><x-icon name="reset" /></a>
        </div>
    </form>
</section>

<section class="panel table-panel">
    <header class="panel-head">
        <div><h2>报价列表</h2><p>主要项目仅展示游玩特色和住宿，餐食已自动忽略。</p></div>
        <span class="record-count">共 {{ method_exists($quoteRows, 'total') ? $quoteRows->total() : count($quoteRows) }} 份</span>
    </header>
    <div class="data-table-wrap">
        <table class="data-table quote-library-table">
            <thead><tr><th>报价名称</th><th>年月</th><th>目的地</th><th>行程</th><th>人数</th><th>人均</th><th>总价</th><th>主要项目</th><th class="actions-cell">操作</th></tr></thead>
            <tbody>
            @forelse($quoteRows as $quote)
                @php
                    $people = (int) ($quote->people_count ?? 0);
                    $total = (float) ($quote->total_amount ?? $quote->total ?? 0);
                    $summary = $quote->highlights ?? $quote->major_items ?? '';
                    if (is_array($summary)) $summary = implode('、', $summary);
                @endphp
                <tr>
                    <td><span class="table-title">{{ $quote->title ?? $quote->customer_title ?? '未命名报价' }}</span><span class="table-sub">{{ $quote->customer_name ?? $quote->source_file ?? '' }}</span></td>
                    <td>{{ $quote->year ?? optional($quote->quote_date ?? null)->format('Y') ?? '-' }}.{{ str_pad((string) ($quote->month ?? optional($quote->quote_date ?? null)->format('n') ?? '-'), 2, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $quote->destination ?? '-' }}</td>
                    <td>{{ $quote->trip_type }}</td>
                    <td>{{ $people }}人</td>
                    <td class="money">¥{{ number_format($people > 0 ? $total / $people : 0, 0) }}</td>
                    <td class="money strong">¥{{ number_format($total, 2) }}</td>
                    <td class="summary-cell" title="{{ $summary }}">{{ $summary ?: '查看详情了解行程项目' }}</td>
                    <td class="actions-cell"><div class="row-actions">
                        <a class="icon-btn" href="{{ Route::has('quotes.show') ? route('quotes.show', $quote) : url('/quotes/'.$quote->id) }}" data-tooltip="查看报价详情" aria-label="查看报价详情"><x-icon name="eye" /></a>
                        <a class="icon-btn" href="{{ route('quotes.preview', $quote) }}" data-tooltip="直接使用报价" aria-label="直接使用报价"><x-icon name="copy" /></a>
                        <a class="icon-btn primary" href="{{ route('quotes.copy.edit', $quote) }}" data-tooltip="复制并微调" aria-label="复制并微调"><x-icon name="edit" /></a>
                        @can('delete', $quote)
                            <form method="POST" action="{{ route('quotes.destroy', $quote) }}" data-confirm="确定删除这份原始报价吗？删除后无法恢复。">@csrf @method('DELETE')<button class="icon-btn danger" type="submit" data-tooltip="删除原始报价" aria-label="删除原始报价"><x-icon name="trash" /></button></form>
                        @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-state"><x-icon name="search" /><strong>没有找到匹配的历史报价</strong><span>调整筛选条件，或新增一份历史报价。</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($quoteRows, 'links'))<div class="pagination-wrap">{{ $quoteRows->withQueryString()->links() }}</div>@endif
</section>
@endsection
