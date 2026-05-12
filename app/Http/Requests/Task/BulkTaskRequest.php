<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class BulkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_ids'   => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'action'     => ['required', 'string', 'in:update_status,update_priority,update_category,delete'],
            'value'      => ['required_unless:action,delete'],
        ];
    }

    public function messages(): array
    {
        return [
            'task_ids.required'     => 'Please provide at least one task ID.',
            'task_ids.*.exists'     => 'One or more selected tasks do not exist.',
            'action.required'       => 'Please specify a bulk action.',
            'action.in'             => 'Invalid action. Allowed: update_status, update_priority, update_category, delete.',
            'value.required_unless' => 'A value is required for this action.',
        ];
    }
}
