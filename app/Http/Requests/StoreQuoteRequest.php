<?php

namespace App\Http\Requests;

use App\Models\Quote;
use Illuminate\Support\Facades\Gate;

class StoreQuoteRequest extends QuoteRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Quote::class);
    }
}
