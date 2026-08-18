<?php

namespace App\Http\Requests;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateQuoteSalesStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('quote'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'sales_status' => ['required', Rule::in(array_keys(Quote::SALES_STATUS_LABELS))],
        ];
    }
}
