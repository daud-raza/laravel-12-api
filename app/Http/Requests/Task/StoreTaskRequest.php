<?php

namespace App\Http\Requests\Task;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'category_id'        => ['nullable', 'exists:categories,id'],
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'status'             => ['nullable', 'in:pending,in_progress,completed'],
            'priority'           => ['nullable', 'in:low,medium,high'],
            'due_date'           => ['nullable', 'date', 'after_or_equal:today'],
            'is_recurring'       => ['boolean'],
            'recurrence_type'    => ['required_if:is_recurring,true', 'nullable', 'in:daily,weekly,monthly'],
            'recurrence_ends_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array {
        return [
            'category_id.exists' => 'Category does not exist',
            'title.required'     => 'Title is required',
            'title.string'       => 'Title must be a string',
            'title.max'          => 'Title must be at most 255 characters long',
            'description.string' => 'Description must be a string',
            'status.in'          => 'Status must be one of: pending, in_progress, completed',
            'priority.in'        => 'Priority must be one of: low, medium, high',
            'due_date.date'               => 'Due date must be a date',
            'due_date.after_or_equal'     => 'Due date must be after or equal to today',
            'recurrence_type.required_if' => 'Recurrence type is required when the task is set to recurring.',
            'recurrence_type.in'          => 'Recurrence type must be one of: daily, weekly, monthly.',
            'recurrence_ends_at.date'     => 'Recurrence end date must be a valid date.',
            'recurrence_ends_at.after'    => 'Recurrence end date must be after today.',
        ];
    }
}
