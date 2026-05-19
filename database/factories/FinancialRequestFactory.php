<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinancialRequest>
 */
class FinancialRequestFactory extends Factory
{
    protected $model = FinancialRequest::class;

    public function definition(): array
    {
        return [
            'uuid'               => Str::uuid()->toString(),
            'amount'             => $this->faker->randomFloat(2, 100, 50000),
            'currency'           => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'EGP']),
            'requester_id'       => \App\Models\User::factory(),
            'status'             => FinancialRequestStatus::Pending,
            'external_reference' => null,
            'idempotency_key'    => Str::uuid()->toString(),
            'metadata'           => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => FinancialRequestStatus::Pending]);
    }

    public function underReview(): static
    {
        return $this->state(['status' => FinancialRequestStatus::UnderReview]);
    }

    public function approved(): static
    {
        return $this->state(['status' => FinancialRequestStatus::Approved]);
    }

    public function processing(): static
    {
        return $this->state(['status' => FinancialRequestStatus::Processing]);
    }

    public function completed(): static
    {
        return $this->state(['status' => FinancialRequestStatus::Completed]);
    }

    public function failed(): static
    {
        return $this->state(['status' => FinancialRequestStatus::Failed]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => FinancialRequestStatus::Cancelled]);
    }
}
