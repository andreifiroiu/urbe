<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['sometimes', 'string', 'timezone'],
            'city' => ['sometimes', 'string', 'max:255'],
            'discovery_openness' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
