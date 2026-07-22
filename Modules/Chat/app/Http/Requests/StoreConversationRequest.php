<?php

namespace Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->user()->id]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'A user to chat with is required.',
            'user_id.exists' => 'That user does not exist.',
            'user_id.not_in' => 'You cannot start a conversation with yourself.',
        ];
    }
}
