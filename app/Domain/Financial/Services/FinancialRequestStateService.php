<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\DTOs\TransitionFinancialRequestData;
use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use App\Domain\Financial\Repositories\Contracts\FinancialRequestRepositoryInterface;
use App\Domain\Financial\States\FinancialRequestStateMachine;
use Illuminate\Support\Facades\DB;

final class FinancialRequestStateService
{
    public function __construct(
        private readonly FinancialRequestRepositoryInterface $repository,
        private readonly FinancialRequestStateMachine $stateMachine,
    ) {}

    public function transition(TransitionFinancialRequestData $data): FinancialRequest
    {
        return DB::transaction(function () use ($data): FinancialRequest {
            $request = $this->repository->findOrFail($data->requestId);

            $metadata = $data->reason !== null
                ? array_merge($data->metadata ?? [], ['transition_reason' => $data->reason])
                : $data->metadata;

            $this->stateMachine->transition($request, $data->targetStatus, $metadata);

            return $request->fresh();
        });
    }

    public function getAllowedTransitions(int $id): array
    {
        $request = $this->repository->findOrFail($id);

        return array_map(
            fn (FinancialRequestStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            $this->stateMachine->getAllowedTransitions($request),
        );
    }
}
