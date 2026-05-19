<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FinancialRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'amount'             => $this->amount,
            'currency'           => $this->currency,
            'status'             => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'external_reference' => $this->external_reference,
            'metadata'           => $this->metadata,
            'is_terminal'        => $this->status->isTerminal(),
            'created_at'         => $this->created_at->toIso8601String(),
            'updated_at'         => $this->updated_at->toIso8601String(),
        ];
    }
}
