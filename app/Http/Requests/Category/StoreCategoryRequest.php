<?php

namespace App\Http\Requests\Category;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array {
        return [
            'name.required'   => 'Name is required',
            'name.string'     => 'Name must be a string',
            'name.max'        => 'Name must be at most 255 characters long',
            'color.string'    => 'Color must be a string',
            'color.regex'     => 'Color must be a valid hex code',
        ];
    }

    public function attributes(): array {
        return [
            'name'  => 'Category name',
            'color' => 'Category color',
        ];  
    }
}
