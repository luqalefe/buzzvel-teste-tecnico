<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Original request, in the employee's local currency.
            $table->decimal('amount', 20, 4);
            $table->string('currency', 3);

            // Immutable snapshot of the exchange rate at creation time.
            // High precision so even large EUR→local rates keep their fidelity.
            $table->decimal('exchange_rate', 20, 10);
            $table->string('rate_source');
            $table->timestamp('rate_fetched_at');

            // Value converted into the base currency (EUR).
            $table->decimal('converted_amount_eur', 20, 4);

            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->text('description')->nullable();
            $table->timestamps();

            // The expiration job scans pending rows by age; the listing screen
            // filters a user's rows by status.
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
