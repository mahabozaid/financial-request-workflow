<?php

declare(strict_types=1);

namespace App\Domain\Financial\Repositories;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use App\Domain\Financial\Repositories\Contracts\FinancialRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentFinancialRequestRepository implements FinancialRequestRepositoryInterface
{
    public function find(int $id): ?FinancialRequest
    {
        return FinancialRequest::query()->find($id);
    }

    public function findOrFail(int $id): FinancialRequest
    {
        $request = $this->find($id);

        if ($request === null) {
            throw (new ModelNotFoundException())->setModel(FinancialRequest::class, $id);
        }

        return $request;
    }

    public function save(FinancialRequest $request): bool
    {
        return $request->save();
    }

    public function create(array $attributes): FinancialRequest
    {
        return FinancialRequest::query()->create($attributes);
    }

    public function findByStatus(FinancialRequestStatus $status): Collection
    {
        return FinancialRequest::query()->byStatus($status)->get();
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return FinancialRequest::query()
            ->forUser($userId)
            ->latest()
            ->paginate($perPage);
    }
}
