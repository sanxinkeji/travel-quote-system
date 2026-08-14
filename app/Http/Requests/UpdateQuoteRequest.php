<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class UpdateQuoteRequest extends QuoteRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('quote'));
    }
}
