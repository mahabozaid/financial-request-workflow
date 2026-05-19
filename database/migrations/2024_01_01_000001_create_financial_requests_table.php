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

            $table->decimal('amount', total: 15, places: 4);
            $table->char('currency', 3);

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            $table->string('status', 50)->default('pending');

            $table->string('external_reference')->nullable()->index();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_requests');
    }
};
