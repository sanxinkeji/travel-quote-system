@extends('layouts.app')

@section('title', ($isCopy ?? false) ? '复制并微调 · 旅游报价工作台' : ((isset($quote) && $quote->exists ? '编辑原报价' : '新增历史报价').' · 旅游报价工作台'))

@section('content')
@php
    $quoteGroups = collect(old('groups', $quote->groups ?? collect()));
    $field = static function ($record, string $key, $default = null) {
        return is_array($record) ? ($record[$key] ?? $default) : ($record->{$key} ?? $default);
    };
    $numberInput = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    };
    $isCopy = $isCopy ?? false;
    $updateUrl = $formAction ?? (isset($quote) && $quote->exists ? route('quotes.update', $quote) : route('quotes.store'));
@endphp
<form class="quote-editor-form" method="POST" action="{{ $updateUrl }}" data-quote-editor>
    @csrf
    @if(($formMethod ?? (isset($quote) && $quote->exists ? 'PUT' : 'POST')) !== 'POST') @method($formMethod ?? 'PUT') @endif
    <input type="hidden" name="status" value="{{ old('status', $quote->status ?? 'historical') }}">
    <section class="page-toolbar detail-toolbar editor-page-toolbar">
        <div>
            <a class="back-link" href="{{ isset($quote) && $quote->exists ? route('quotes.show', $quote) : route('quotes.index') }}"><x-icon name="arrow-left" />{{ isset($quote) && $quote->exists ? '返回报价详情' : '返回历史报价库' }}</a>
            <h1>{{ $isCopy ? '复制并微调' : (isset($quote) && $quote->exists ? '编辑原报价' : '新增历史报价') }}</h1>
            <p>{{ $isCopy ? '当前内容来自历史报价，保存后会生成一条属于当前账号的新报价，原报价不会改变。' : '报价信息、行程分组和所有项目字段均可增删修改，保存后进入客户预览。' }}</p>
        </div>
        <div class="toolbar-actions">
            <button class="btn" type="submit" name="after_save" value="stay">保存报价</button>
            <button class="btn primary" type="submit" name="after_save" value="preview"><x-icon name="eye" />保存并预览</button>
        </div>
    </section>

    <div class="editor-layout">
        <div class="editor-main">
            <section class="panel quote-information" data-information-panel>
                <header class="panel-head">
                    <div><h2>报价单信息</h2><p>这些内容会同步到客户预览、图片和表格。</p></div>
                    <button class="icon-btn information-toggle" type="button" data-toggle-information data-tooltip="收起基础信息" aria-label="收起基础信息" aria-expanded="true" aria-controls="quote-information-fields"><x-icon name="chevron-up" /></button>
                </header>
                <div class="information-table-wrap" id="quote-information-fields">
                    <table class="quote-information-table">
                        <colgroup>
                            @foreach(range(1, 5) as $column)
                                <col class="information-label-column">
                                <col class="information-value-column">
                            @endforeach
                        </colgroup>
                        <tbody>
                        <tr>
                            <th scope="row">报价单标题</th>
                            <td colspan="4"><input aria-label="报价单标题" name="title" value="{{ old('title', $quote->title ?? '行程报价单') }}" required></td>
                            <th scope="row">客户行程标题</th>
                            <td colspan="4"><input aria-label="客户行程标题" name="customer_title" value="{{ old('customer_title', $quote->customer_title ?? '') }}" placeholder="如：某某公司 · 惠州两天一夜"></td>
                        </tr>
                        <tr data-information-row="trip">
                            <th scope="row">年份</th>
                            <td><input aria-label="年份" type="number" name="year" min="2000" max="2100" value="{{ old('year', $quote->year ?? date('Y')) }}" required></td>
                            <th scope="row">月份</th>
                            <td><select aria-label="月份" name="month" required>
                                @foreach(range(1, 12) as $month)
                                    <option value="{{ $month }}" @selected((int) old('month', $quote->month ?? date('n')) === $month)>{{ $month }}月</option>
                                @endforeach
                            </select></td>
                            <th scope="row">目的地</th>
                            <td><input aria-label="目的地" name="destination" value="{{ old('destination', $quote->destination ?? '') }}" required></td>
                            <th scope="row">人数</th>
                            <td><input aria-label="人数" type="number" name="people_count" min="1" value="{{ old('people_count', $quote->people_count ?? 1) }}" data-people-count required></td>
                            <th scope="row">行程类型</th>
                            <td><select aria-label="行程类型" name="duration_days" required>
                                @foreach([1 => '一日游', 2 => '两天一夜', 3 => '三天两夜', 4 => '四天三夜'] as $days => $label)
                                    <option value="{{ $days }}" @selected((int) old('duration_days', $quote->duration_days ?? 1) === $days)>{{ $label }}</option>
                                @endforeach
                            </select></td>
                        </tr>
                        <tr data-information-row="contact">
                            <th scope="row">策划人</th>
                            <td><input aria-label="策划人" name="planner_name" value="{{ old('planner_name', $quote->planner_name ?? auth()->user()?->name) }}"></td>
                            <th scope="row">微信号</th>
                            <td><input aria-label="微信号" name="wechat" value="{{ old('wechat', $quote->wechat ?? '') }}"></td>
                            <th scope="row">联系方式</th>
                            <td><input aria-label="联系方式" name="phone" value="{{ old('phone', $quote->phone ?? '') }}"></td>
                            <th scope="row">执行方</th>
                            <td colspan="3"><input aria-label="执行方" name="executor" value="{{ old('executor', $quote->executor ?? '') }}"></td>
                        </tr>
                        <tr data-information-row="reminder">
                            <th scope="row">提示标题</th>
                            <td><input aria-label="提示标题" name="reminder_title" value="{{ old('reminder_title', $quote->reminder_title ?? '温馨提示') }}"></td>
                            <th scope="row">温馨提示内容</th>
                            <td colspan="7"><textarea aria-label="温馨提示内容" name="reminder_text" rows="2">{{ old('reminder_text', $quote->reminder_text ?? '') }}</textarea></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel group-editor-panel">
                <header class="panel-head"><div><h2>行程与费用</h2><p>普通项目按数量 × 单价计算；发票税费按非税项目总额自动计算。</p></div><button class="btn small" type="button" data-add-group><x-icon name="plus" />添加分组</button></header>
                <div class="groups-editor" data-groups>
                    @forelse($quoteGroups as $groupIndex => $group)
                        @php
                            $groupName = $field($group, 'name', 'DAY '.str_pad($groupIndex + 1, 2, '0', STR_PAD_LEFT));
                            $groupType = $field($group, 'type', 'day');
                            $groupSort = $field($group, 'sort_order', $groupIndex);
                            $groupSubtotal = $field($group, 'subtotal', 0);
                            $groupItems = collect($field($group, 'items', collect()));
                        @endphp
                        <section class="quote-group" data-group data-group-index="{{ $groupIndex }}">
                            <header class="group-head">
                                <div class="group-title"><span class="group-dot"></span><input name="groups[{{ $groupIndex }}][name]" value="{{ $groupName }}" aria-label="分组名称" required><input type="hidden" name="groups[{{ $groupIndex }}][type]" value="{{ $groupType }}"><input type="hidden" name="groups[{{ $groupIndex }}][sort_order]" value="{{ $groupSort }}"></div>
                                <div class="group-head-actions"><span>小计 <strong data-group-total>¥{{ number_format((float) $groupSubtotal, 2) }}</strong></span><button class="icon-btn danger" type="button" data-remove-group data-tooltip="删除分组" aria-label="删除分组"><x-icon name="trash" /></button></div>
                            </header>
                            <div class="items-editor" data-items>
                                <div class="item-grid item-header"><span aria-hidden="true"></span><span>#</span><span>预估时段</span><span>项目名称</span><span>单位</span><span>数量</span><span>单价</span><span>实际总价</span><span>备注 / 其他</span><span></span></div>
                                @foreach($groupItems as $itemIndex => $item)
                                    @php
                                        $isTax = filter_var($field($item, 'is_tax', false), FILTER_VALIDATE_BOOL);
                                        $itemSort = $field($item, 'sort_order', $itemIndex);
                                    @endphp
                                    <div class="item-grid {{ $isTax ? 'tax-row' : '' }}" data-item data-tax-item="{{ $isTax ? 'true' : 'false' }}" data-item-index="{{ $itemIndex }}">
                                        <button class="item-drag-handle" type="button" data-drag-handle data-tooltip="拖动排序" aria-label="拖动第 {{ $itemIndex + 1 }} 项排序"><span aria-hidden="true"></span></button>
                                        <span class="item-number">{{ $itemIndex + 1 }}</span>
                                        <input name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][time]" value="{{ $field($item, 'time', '') }}" aria-label="预估时段">
                                        <input name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][name]" value="{{ $field($item, 'name', '') }}" aria-label="项目名称" data-item-name required>
                                        <input name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][unit]" value="{{ $field($item, 'unit', '') }}" aria-label="单位" data-item-unit>
                                        <input type="number" step="0.01" name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][quantity]" value="{{ $numberInput($field($item, 'quantity', 0)) }}" aria-label="数量" data-quantity>
                                        <input type="number" step="0.01" name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][unit_price]" value="{{ $field($item, 'unit_price', 0) }}" aria-label="单价" data-unit-price {{ $isTax ? 'readonly' : '' }}>
                                        <input type="number" step="0.01" name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][actual_total]" value="{{ $field($item, 'actual_total', '') }}" aria-label="实际总价" data-actual-total {{ $isTax ? 'readonly' : '' }}>
                                        <input name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][note]" value="{{ $field($item, 'note', '') }}" aria-label="备注" placeholder="{{ $isTax ? '税基自动汇总 / 税额自动计算' : '' }}">
                                        <input type="hidden" name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][is_tax]" value="{{ $isTax ? 1 : 0 }}" data-is-tax>
                                        <input type="hidden" name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][tax_rate]" value="{{ $field($item, 'tax_rate', 0) }}" data-tax-rate>
                                        <input type="hidden" name="groups[{{ $groupIndex }}][items][{{ $itemIndex }}][sort_order]" value="{{ $itemSort }}">
                                        <button class="icon-btn danger" type="button" data-remove-item data-tooltip="删除项目" aria-label="删除项目"><x-icon name="trash" /></button>
                                    </div>
                                @endforeach
                            </div>
                            <footer class="group-foot"><button class="btn ghost small" type="button" data-add-item><x-icon name="plus" />添加项目</button></footer>
                        </section>
                    @empty
                        <section class="quote-group" data-group data-group-index="0">
                            <header class="group-head"><div class="group-title"><span class="group-dot"></span><input name="groups[0][name]" value="DAY 01" aria-label="分组名称" required><input type="hidden" name="groups[0][type]" value="day"><input type="hidden" name="groups[0][sort_order]" value="0"></div><div class="group-head-actions"><span>小计 <strong data-group-total>¥0.00</strong></span><button class="icon-btn danger" type="button" data-remove-group data-tooltip="删除分组"><x-icon name="trash" /></button></div></header>
                            <div class="items-editor" data-items><div class="item-grid item-header"><span aria-hidden="true"></span><span>#</span><span>预估时段</span><span>项目名称</span><span>单位</span><span>数量</span><span>单价</span><span>实际总价</span><span>备注 / 其他</span><span></span></div></div>
                            <footer class="group-foot"><button class="btn ghost small" type="button" data-add-item><x-icon name="plus" />添加项目</button></footer>
                        </section>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="editor-summary">
            <section class="panel summary-panel">
                <h2>费用汇总</h2>
                <div data-summary-groups></div>
                <div class="summary-line total"><span>最终总价</span><strong data-grand-total>¥{{ number_format((float) ($quote->total_amount ?? 0), 2) }}</strong></div>
                <div class="summary-line"><span>人均/位</span><strong data-per-person>¥{{ number_format((float) ($quote->per_person_amount ?? 0), 2) }}</strong></div>
                <p class="calculation-note">最终总价 = 每天小计 + 其他项小计。税费行不重复计入税基。</p>
            </section>
        </aside>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('vendor/sortable/Sortable.min.js') }}?v={{ filemtime(public_path('vendor/sortable/Sortable.min.js')) }}"></script>
<script src="{{ asset('js/quote-editor.js') }}?v={{ filemtime(public_path('js/quote-editor.js')) }}"></script>
@endpush
