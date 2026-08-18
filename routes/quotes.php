<?php

use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{quote}/copy/edit', [QuoteController::class, 'copyEdit'])->name('quotes.copy.edit');
    Route::post('/quotes/{quote}/copy/store', [QuoteController::class, 'storeCopy'])->name('quotes.copy.store');
    Route::patch('/quotes/{quote}/sales-status', [QuoteController::class, 'updateSalesStatus'])->name('quotes.sales-status');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::put('/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::get('/quotes/{quote}/preview', [QuoteController::class, 'preview'])->name('quotes.preview');
    Route::post('/quotes/{quote}/copy', [QuoteController::class, 'copy'])->name('quotes.copy');
});
