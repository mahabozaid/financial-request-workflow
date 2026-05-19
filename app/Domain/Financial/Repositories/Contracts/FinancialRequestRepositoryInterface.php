<?php

declare(strict_types=1);

namespace App\Domain\Financial\Repositories\Contracts;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface FinancialRequestRepositoryInterface
{
    public function find(int $id): ?FinancialRequest;

    public function findOrFail(int $id): FinancialRequest;

    public function save(FinancialRequest $request): bool;

    public function create(array $attributes): FinancialRequest;

    /** @return Collection<int, FinancialRequest> */
    public function findByStatus(FinancialRequestStatus $status): Collection;

    /** @return LengthAwarePaginator<FinancialRequest> */
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator;
}
