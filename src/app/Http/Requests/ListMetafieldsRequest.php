<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMetafieldsRequest extends FormRequest
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
            'namespace' => ['nullable', 'string', 'max:255'],
            'first' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
