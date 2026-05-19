<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\DTOs\CreateFinancialRequestData;
use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use App\Domain\Financial\Repositories\Contracts\FinancialRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

final class FinancialRequestService
{
    public function __construct(
        private readonly FinancialRequestRepositoryInterface $repository,
    ) {}

    public function create(CreateFinancialRequestData $data): FinancialRequest
    {
        $request = $this->repository->create([
            'amount'             => $data->amount,
            'currency'           => $data->currency,
            'user_id'            => $data->userId,
            'status'             => FinancialRequestStatus::Pending,
            'external_reference' => $data->externalReference,
            'metadata'           => $data->metadata,
        ]);

        Log::info('FinancialRequestService: request created', ['id' => $request->id]);

        return $request;
    }

    public function find(int $id): ?FinancialRequest
    {
        return $this->repository->find($id);
    }

    public function findOrFail(int $id): FinancialRequest
    {
        return $this->repository->findOrFail($id);
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($userId, $perPage);
    }
}
