<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator as ValidationContract;
class RegisterRequest extends FormRequest
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
            'email' => "required|email|unique:users",
            'password' => 'required|min:6|confirmed|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/',
            'password_confirmation' => 'required|same:password',
            'full_name' => 'required|min:3|max:255|string',
            'job_title' => 'required|min:3|max:255|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'  => "The email field is required.",
            'email.email'     => "The email must be a valid email address.",
            'email.unique'    => "Invalid Email.",
            'password.required' => "The password field is required.",
            'password.min'     => "The password must be at least 6 characters.",
            'password.confirmed' => "The password confirmation does not match.",
            'password.regex'   => "Password must be at least 8 characters and include uppercase, lowercase, numbers, and symbols.",
            'password_confirmation.required' => "The password confirmation field is required.",
            'full_name.required'  => "The name field is required.",
            'full_name.min'       => "The name must be at least 3 characters.",
            'full_name.max'       => "The name may not be greater than 255 characters.",
            'job_title.required'  => "The job title field is required.",
            'job_title.min'       => "The job title must be at least 3 characters.",
            'job_title.max'       => "The job title may not be greater than 255 characters.",
            'job_title.string'    => "The job title must be a string.",
            'avatar.image'       => "The image must be an image.",
            'avatar.mimes'       => "The image must be a file of type: jpeg, jpg, png, gif.",
            'avatar.max'         => "The image may not be greater than 2MB.",

        ];
    }

    protected function failedValidation(ValidationContract $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];

        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $cleaned[$key] = trim($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        $this->merge($cleaned);
    }
}
