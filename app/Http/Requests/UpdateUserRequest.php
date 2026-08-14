<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'role' => ['required', Rule::in(['admin', 'employee'])],
        ];
    }
}
