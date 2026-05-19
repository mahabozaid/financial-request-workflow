<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateFinancialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'             => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'currency'           => ['required', 'string', 'size:3', 'alpha'],
            'external_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata'           => ['sometimes', 'nullable', 'array'],
        ];
    }
}
