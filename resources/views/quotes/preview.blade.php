@extends('layouts.app')

@section('title', '客户报价预览 · 旅游报价工作台')

@section('content')
<section class="page-toolbar detail-toolbar preview-toolbar">
    <div>
        @can('update', $quote)
            <a class="back-link" href="{{ route('quotes.edit', $quote) }}"><x-icon name="arrow-left" />返回报价编辑</a>
        @else
            <a class="back-link" href="{{ route('quotes.show', $quote) }}"><x-icon name="arrow-left" />返回报价详情</a>
        @endcan
        <h1>客户报价预览</h1>
        <p>这是客户会看到的版本，内部字段不会出现在报价图片中。</p>
    </div>
    <div class="toolbar-actions">
        <button class="icon-btn labeled" type="button" data-copy-image data-tooltip="复制为图片" aria-label="复制为图片"><x-icon name="copy" /><span>复制图片</span></button>
        <button class="icon-btn labeled" type="button" data-download-image data-tooltip="下载报价图片" aria-label="下载报价图片"><x-icon name="image" /><span>下载图片</span></button>
        <button class="icon-btn labeled primary" type="button" data-export-table data-filename="{{ $quote->title ?? '行程报价' }}" data-tooltip="导出 Excel 表格" aria-label="导出 Excel 表格"><x-icon name="sheet" /><span>导出表格</span></button>
    </div>
</section>

<div class="preview-canvas">
    @include('quotes._document', ['quote' => $quote, 'class' => 'preview-document'])
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}?v={{ filemtime(public_path('vendor/html2canvas/html2canvas.min.js')) }}"></script>
<script src="{{ asset('vendor/xlsx-js-style/xlsx.bundle.js') }}?v={{ filemtime(public_path('vendor/xlsx-js-style/xlsx.bundle.js')) }}"></script>
<script src="{{ asset('js/quote-actions.js') }}?v={{ filemtime(public_path('js/quote-actions.js')) }}"></script>
@endpush
