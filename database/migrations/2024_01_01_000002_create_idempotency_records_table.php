<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->id();

            // Unique key per operation — the definitive completed-or-not record.
            $table->string('idempotency_key', 255)->unique();

            // pending | processing | completed | failed
            $table->string('status', 20)->default('pending');

            // JSON-encoded ERP response stored for replay/debugging.
            $table->text('result')->nullable();

            $table->timestamp('completed_at')->nullable();

            // TTL-based cleanup support — a scheduled command removes expired records.
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
