<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetafieldRequest extends FormRequest
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
            'owner_id' => ['required', 'string', 'max:255'],
            'namespace' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:64'],
            'value' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:64'],
        ];
    }
}
