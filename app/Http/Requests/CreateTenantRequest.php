<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateTenantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'platform_type' => ['required', 'in:shopify,shopware'],
            'platform_url' => ['required', 'url', 'max:500'],
            'api_credentials' => ['required', 'array'],
            'api_credentials.api_key' => ['required', 'string'],
            'api_credentials.api_secret' => ['required', 'string'],
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
