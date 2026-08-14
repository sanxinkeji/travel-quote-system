@php
    $quoteGroups = $quote->groups ?? collect();
    $quotePeople = (int) ($quote->people_count ?? 0);
    $quoteTotal = (float) ($quote->total_amount ?? $quote->total ?? 0);
    $quotePerPerson = $quotePeople > 0 ? $quoteTotal / $quotePeople : 0;
@endphp
<article class="quote-document {{ $class ?? '' }}" data-quote-document
    data-export-title="{{ $quote->title ?? '行程报价单' }}"
    data-export-customer-title="{{ $quote->customer_title ?? (($quote->customer_name ?? '') . ' ' . ($quote->destination ?? '') . '定制行程') }}"
    data-export-planner="{{ $quote->planner_name ?? '' }}"
    data-export-wechat="{{ $quote->wechat ?? '' }}"
    data-export-phone="{{ $quote->phone ?? '' }}"
    data-export-executor="{{ $quote->executor ?? '' }}"
    data-export-reminder-title="{{ $quote->reminder_title ?? '' }}"
    data-export-reminder-text="{{ $quote->reminder_text ?? '' }}"
    data-export-people="{{ $quotePeople }}"
    data-export-per-person="{{ number_format($quotePerPerson, 2, '.', '') }}"
    data-export-total="{{ number_format($quoteTotal, 2, '.', '') }}">
    <header class="document-head">
        <div>
            <h2>{{ $quote->title ?? '行程报价单' }}</h2>
            <h3>{{ $quote->customer_title ?? (($quote->customer_name ?? '') . ' ' . ($quote->destination ?? '') . '定制行程') }}</h3>
        </div>
        <div class="document-contact">
            <span>策划人：{{ $quote->planner_name ?? '-' }}</span>
            <span>微信号：{{ $quote->wechat ?? '-' }}　联系方式：{{ $quote->phone ?? '-' }}</span>
            <span>执行方：{{ $quote->executor ?? '-' }}</span>
        </div>
    </header>
    @if(($quote->reminder_text ?? '') !== '')
        <div class="document-notice">
            <strong>{{ $quote->reminder_title ?? '温馨提示' }}</strong>
            <span>{{ $quote->reminder_text }}</span>
        </div>
    @endif
    <div class="document-table-wrap">
        <table class="quote-table" data-export-table-source>
            <thead>
            <tr>
                <th>时间/日期</th><th>序号</th><th>预估时段</th><th>项目名称</th><th>单位</th><th class="numeric">数量</th><th class="numeric">单价</th><th class="numeric">总价</th><th>备注/其他</th>
            </tr>
            </thead>
            <tbody>
            @forelse($quoteGroups as $group)
                @php
                    $items = $group->items ?? collect();
                    $groupType = $group->type ?? 'day';
                    $groupTotal = (float) ($group->subtotal ?? collect($items)->sum(fn ($item) => (float) ($item->line_total ?? $item->actual_total ?? ((float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0)))));
                @endphp
                @foreach($items as $item)
                    <tr>
                        @if($loop->first)
                            <td class="group-cell {{ $groupType === 'other' ? 'other' : '' }}" data-export-group-type="{{ $groupType }}" rowspan="{{ max(count($items), 1) }}">{{ $group->name ?? '行程' }}</td>
                        @endif
                        <td class="center">{{ $loop->iteration }}</td>
                        <td class="center">{{ $item->time ?? $item->time_slot ?? '' }}</td>
                        <td>{{ $item->name ?? $item->item_name ?? '' }}</td>
                        <td class="center">{{ $item->unit ?? '' }}</td>
                        <td class="numeric center">{{ ((float) ($item->quantity ?? 0)) != 0 ? rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') : '' }}</td>
                        <td class="numeric center">{{ ((float) ($item->unit_price ?? 0)) != 0 ? '¥'.number_format((float) $item->unit_price, 2) : '' }}</td>
                        <td class="numeric center strong">¥{{ number_format((float) ($item->line_total ?? $item->actual_total ?? ((float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0))), 2) }}</td>
                        <td>{{ $item->note ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td class="center" colspan="7">{{ $group->name ?? '本组' }}小计</td>
                    <td class="numeric center">¥{{ number_format($groupTotal, 2) }}</td>
                    <td></td>
                </tr>
            @empty
                <tr><td class="empty-cell" colspan="9">该报价暂时没有行程项目</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <footer class="document-total">
        <span>汇总（人数）：<strong>{{ $quotePeople }}</strong> 人</span>
        <span>人均/位：<strong>¥{{ number_format($quotePerPerson, 2) }}</strong></span>
        <span>总计：<strong>¥{{ number_format($quoteTotal, 2) }}</strong></span>
    </footer>
</article>
