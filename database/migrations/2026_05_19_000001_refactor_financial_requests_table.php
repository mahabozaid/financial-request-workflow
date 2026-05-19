<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_requests', function (Blueprint $table): void {
            // Drop composite index before touching its columns
            $table->dropIndex(['requester_id', 'status']);

            // uuid and idempotency_key are removed (their unique indexes drop automatically)
            $table->dropColumn(['uuid', 'idempotency_key']);

            $table->renameColumn('requester_id', 'user_id');
        });

        Schema::table('financial_requests', function (Blueprint $table): void {
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_requests', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->renameColumn('user_id', 'requester_id');
            $table->uuid('uuid')->unique()->after('id');
            $table->string('idempotency_key', 255)->unique()->after('external_reference');
        });

        Schema::table('financial_requests', function (Blueprint $table): void {
            $table->index(['requester_id', 'status']);
        });
    }
};
