<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Financial\DTOs\CreateFinancialRequestData;
use App\Domain\Financial\DTOs\TransitionFinancialRequestData;
use App\Domain\Financial\Exceptions\InvalidStateTransitionException;
use App\Domain\Financial\Services\FinancialRequestService;
use App\Domain\Financial\Services\FinancialRequestStateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateFinancialRequestRequest;
use App\Http\Requests\TransitionFinancialRequestRequest;
use App\Http\Resources\FinancialRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class FinancialRequestController extends Controller
{
    public function __construct(
        private readonly FinancialRequestService      $service,
        private readonly FinancialRequestStateService $stateService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = $this->service->paginateForUser(
            userId: $request->user()->id,
        );

        return FinancialRequestResource::collection($requests);
    }

    public function store(CreateFinancialRequestRequest $request): JsonResponse
    {
        $financialRequest = $this->service->create(
            CreateFinancialRequestData::fromArray(
                array_merge($request->validated(), ['user_id' => $request->user()->id])
            )
        );

        return (new FinancialRequestResource($financialRequest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(int $id): FinancialRequestResource
    {
        return new FinancialRequestResource($this->service->findOrFail($id));
    }

    public function transition(TransitionFinancialRequestRequest $request, int $id): JsonResponse
    {
        try {
            $updated = $this->stateService->transition(
                TransitionFinancialRequestData::fromArray(
                    array_merge($request->validated(), ['request_id' => $id])
                )
            );

            return (new FinancialRequestResource($updated))->response();

        } catch (InvalidStateTransitionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'from'    => $e->from->value,
                'to'      => $e->to->value,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function allowedTransitions(int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->stateService->getAllowedTransitions($id),
        ]);
    }
}
