<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTenantRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,pending_setup,sync_error,suspended'],
            'platform_url' => ['sometimes', 'url', 'max:500'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    /**
     * Override failed validation to return consistent JSON format.
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = collect($validator->errors())
            ->map(function ($messages, $field) {
                return [
                    'field' => $field,
                    'message' => $messages[0],
                ];
            })
            ->values();

        throw new HttpResponseException(
            response()->json([
                'errors' => $errors
            ], 422)
        );
    }
}
