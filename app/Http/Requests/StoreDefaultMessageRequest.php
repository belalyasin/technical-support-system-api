<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDefaultMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('default_messages', 'body')->ignore($this->route('default_message')),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:default_messages,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
