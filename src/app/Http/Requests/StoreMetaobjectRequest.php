<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetaobjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'fields' => ['nullable', 'array'],
            'fields.*.key' => ['required_with:fields', 'string', 'max:64'],
            'fields.*.value' => ['required_with:fields', 'string'],
        ];
    }
}
