<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'value'      => $this->valueRules(),
        ];
    }

    /**
     * Validate `value` against the chosen action. For update_category the
     * target category must belong to the authenticated user, closing a
     * cross-user assignment gap.
     */
    protected function valueRules(): array
    {
        return match ($this->input('action')) {
            'update_status'   => ['required', 'in:pending,in_progress,completed'],
            'update_priority' => ['required', 'in:low,medium,high'],
            'update_category' => [
                'required',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
            default           => ['sometimes'],
        };
    }

    public function messages(): array
    {
        return [
            'task_ids.required'     => 'Please provide at least one task ID.',
            'task_ids.*.exists'     => 'One or more selected tasks do not exist.',
            'action.required'       => 'Please specify a bulk action.',
            'action.in'             => 'Invalid action. Allowed: update_status, update_priority, update_category, delete.',
            'value.required'        => 'A value is required for this action.',
            'value.in'              => 'The provided value is not valid for this action.',
            'value.exists'          => 'The selected category does not exist or does not belong to you.',
        ];
    }
}
