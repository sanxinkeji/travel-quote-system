@can('update', $quote)
    <form class="sales-status-form" method="POST" action="{{ route('quotes.sales-status', $quote) }}">
        @csrf
        @method('PATCH')
        <label class="sales-status-select {{ $quote->sales_status ?? \App\Models\Quote::SALES_FOLLOWING }}">
            <span class="sr-only">跟进状态</span>
            <select name="sales_status" aria-label="修改跟进状态" onchange="this.form.requestSubmit()">
                @foreach(\App\Models\Quote::SALES_STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(($quote->sales_status ?? \App\Models\Quote::SALES_FOLLOWING) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>
@else
    <span class="sales-status-badge {{ $quote->sales_status ?? \App\Models\Quote::SALES_FOLLOWING }}">{{ $quote->sales_status_label }}</span>
@endcan
