<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentRequest
 */
class PaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency->value,
            'exchange_rate' => (float) $this->exchange_rate,
            'rate_source' => $this->rate_source,
            'rate_fetched_at' => $this->rate_fetched_at?->toIso8601String(),
            'converted_amount_eur' => (float) $this->converted_amount_eur,
            'status' => $this->status->value,
            'description' => $this->description,
            // Only meaningful while pending; lets the UI render a 48h countdown.
            'expires_at' => $this->status === PaymentStatus::Pending
                ? $this->expiresAt()?->toIso8601String()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
