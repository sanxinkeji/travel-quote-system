<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('users')],
            'role' => ['required', Rule::in(['admin', 'employee'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
