<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaobjectRequest extends FormRequest
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
            'handle' => ['nullable', 'string', 'max:255'],
            'fields' => ['nullable', 'array'],
            'fields.*.key' => ['required_with:fields', 'string', 'max:64'],
            'fields.*.value' => ['required_with:fields', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('handle') && ! $this->filled('fields')) {
                $validator->errors()->add('fields', 'Provide at least handle or fields to update.');
            }
        });
    }
}
