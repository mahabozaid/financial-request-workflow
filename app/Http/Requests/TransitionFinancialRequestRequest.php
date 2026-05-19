<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class TransitionFinancialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_status' => ['required', 'string', new Enum(FinancialRequestStatus::class)],
            'reason'        => ['sometimes', 'nullable', 'string', 'max:1000'],
            'metadata'      => ['sometimes', 'nullable', 'array'],
        ];
    }
}
