@extends('layouts.app')

@section('title', ($quote->title ?? '报价详情').' · 旅游报价工作台')

@section('content')
<section class="page-toolbar detail-toolbar">
    <div>
        <a class="back-link" href="{{ route('quotes.index') }}"><x-icon name="arrow-left" />返回历史报价库</a>
        <h1>{{ $quote->title ?? '历史报价详情' }}</h1>
        <p>只读查看原始报价，确认后可直接复制或进入微调。</p>
    </div>
    <div class="toolbar-actions">
        @can('update', $quote)
            <a class="btn" href="{{ route('quotes.edit', $quote) }}"><x-icon name="edit" />编辑原报价</a>
        @endcan
        @can('delete', $quote)
            <form method="POST" action="{{ route('quotes.destroy', $quote) }}" data-confirm="确定删除这份原始报价吗？删除后无法恢复。">@csrf @method('DELETE')<button class="icon-btn danger labeled" type="submit" data-tooltip="删除原始报价" aria-label="删除原始报价"><x-icon name="trash" /><span>删除</span></button></form>
        @endcan
        <a class="btn" href="{{ route('quotes.preview', $quote) }}"><x-icon name="copy" />直接使用</a>
        <a class="btn primary" href="{{ route('quotes.copy.edit', $quote) }}"><x-icon name="edit" />复制并微调</a>
    </div>
</section>

<section class="detail-meta-strip">
    <div><span>报价年月</span><strong>{{ $quote->year ?? '-' }}年{{ $quote->month ?? '-' }}月</strong></div>
    <div><span>目的地</span><strong>{{ $quote->destination ?? '-' }}</strong></div>
    <div><span>行程类型</span><strong>{{ $quote->trip_type }}</strong></div>
    <div><span>报价人数</span><strong>{{ $quote->people_count ?? 0 }}人</strong></div>
    <div><span>创建人</span><strong>{{ $quote->createdBy?->name ?? $quote->creator?->name ?? '-' }}</strong></div>
    <div><span>更新时间</span><strong>{{ $quote->updated_at?->format('Y-m-d H:i') ?? '-' }}</strong></div>
</section>

@include('quotes._document', ['quote' => $quote, 'class' => 'detail-document'])
@endsection
