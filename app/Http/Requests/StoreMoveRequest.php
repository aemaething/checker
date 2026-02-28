<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_row' => ['required', 'integer', 'between:0,7'],
            'from_col' => ['required', 'integer', 'between:0,7'],
            'to_row' => ['required', 'integer', 'between:0,7'],
            'to_col' => ['required', 'integer', 'between:0,7'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_row.required' => 'The starting row is required.',
            'from_col.required' => 'The starting column is required.',
            'to_row.required' => 'The destination row is required.',
            'to_col.required' => 'The destination column is required.',
            'from_row.between' => 'The starting row must be between 0 and 7.',
            'from_col.between' => 'The starting column must be between 0 and 7.',
            'to_row.between' => 'The destination row must be between 0 and 7.',
            'to_col.between' => 'The destination column must be between 0 and 7.',
        ];
    }
}
