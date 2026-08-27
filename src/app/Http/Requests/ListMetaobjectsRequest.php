<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMetaobjectsRequest extends FormRequest
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
            'first' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
