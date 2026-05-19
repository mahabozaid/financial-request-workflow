<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // amount stored as string to avoid IEEE 754 floating-point rounding.
            // Application layer uses bcmath for arithmetic.
            $table->decimal('amount', total: 15, places: 4);
            $table->char('currency', 3);

            $table->unsignedBigInteger('requester_id');
            $table->foreign('requester_id')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            $table->string('status', 50)->default('pending');

            $table->string('external_reference')->nullable()->index();

            // UNIQUE — the true DB-level idempotency guarantee.
            // The application check is for UX; this constraint is the safety net.
            $table->string('idempotency_key', 255)->unique();

            // json works on both SQLite (testing) and PostgreSQL (production).
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for common query patterns.
            $table->index(['requester_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_requests');
    }
};
