<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Exceptions\ImmutableAttributeException;
use App\Exceptions\InvalidStatusTransitionException;
use Database\Factories\PaymentRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PaymentRequest extends Model
{
    /** @use HasFactory<PaymentRequestFactory> */
    use HasFactory;

    /**
     * Once set at creation these attributes must never change, so the recorded
     * exchange rate stays a trustworthy audit trail (US-B2).
     *
     * @var list<string>
     */
    public const IMMUTABLE = ['exchange_rate', 'rate_source', 'rate_fetched_at'];

    /**
     * A pending request is automatically expired this many hours after it was
     * created. The single source of truth for the 48h rule (US-C1).
     */
    public const PENDING_TTL_HOURS = 48;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'exchange_rate',
        'rate_source',
        'rate_fetched_at',
        'converted_amount_eur',
        'status',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            // Match the decimal(20,10) column so the stored rate keeps full fidelity.
            'exchange_rate' => 'decimal:10',
            'rate_fetched_at' => 'datetime',
            'converted_amount_eur' => 'decimal:2',
            'status' => PaymentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Enforce the immutability invariant at the model layer: any attempt to
        // mutate a recorded rate on an existing row is rejected outright.
        static::updating(function (self $request): void {
            foreach (self::IMMUTABLE as $attribute) {
                if ($request->isDirty($attribute)) {
                    throw new ImmutableAttributeException($attribute);
                }
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The moment a still-pending request will be auto-expired. Only meaningful
     * while the request is pending.
     */
    public function expiresAt(): ?Carbon
    {
        return $this->created_at?->copy()->addHours(self::PENDING_TTL_HOURS);
    }

    /**
     * Move a pending request into a terminal status. Anything that is no longer
     * pending cannot transition again (US-B5, US-C1).
     */
    public function transitionTo(PaymentStatus $status): void
    {
        if (! $this->status->isPending()) {
            throw new InvalidStatusTransitionException($this->status, $status);
        }

        $this->update(['status' => $status]);
    }

    /**
     * @param  Builder<PaymentRequest>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * Requests the given user may see: their own, or everyone's if they are
     * finance. Centralises the "employee sees own / finance sees all" rule.
     *
     * @param  Builder<PaymentRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isFinance()) {
            $query->forUser($user);
        }
    }

    /**
     * @param  Builder<PaymentRequest>  $query
     */
    public function scopeWithStatus(Builder $query, PaymentStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * Pending requests created on or before the given cut-off (used by the
     * 48h expiration job).
     *
     * @param  Builder<PaymentRequest>  $query
     */
    public function scopeExpirable(Builder $query, Carbon $threshold): void
    {
        // Strictly older than the cut-off: "pending for MORE than 48h" (US-C1).
        $query->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '<', $threshold);
    }
}
