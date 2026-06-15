<?php

namespace App\Http\Controllers\Api;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest\IndexPaymentRequestRequest;
use App\Http\Requests\PaymentRequest\StorePaymentRequestRequest;
use App\Http\Requests\PaymentRequest\UpdatePaymentRequestRequest;
use App\Http\Resources\PaymentRequestResource;
use App\Models\PaymentRequest;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PaymentRequestController extends Controller
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    /**
     * US-B3 — list payment requests. Employees see only their own; finance sees
     * everyone's. Optionally filtered by status.
     */
    public function index(IndexPaymentRequestRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = PaymentRequest::query()->with('user')->latest();

        if (! $user->isFinance()) {
            $query->forUser($user);
        }

        if ($status = $request->validated('status')) {
            $query->withStatus(PaymentStatus::from($status));
        }

        $perPage = (int) ($request->validated('per_page') ?? 15);

        return PaymentRequestResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Preview the EUR conversion for an amount/currency without persisting
     * anything — powers the live preview in the "new request" form.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999999999.99'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
        ]);

        $currency = Currency::from($validated['currency']);
        $rate = $this->exchangeRates->fetch($currency);

        return response()->json([
            'data' => [
                'currency' => $currency->value,
                'exchange_rate' => (float) $rate->rate,
                'rate_source' => $rate->source,
                'converted_amount_eur' => (float) $rate->convertToBase($validated['amount']),
            ],
        ]);
    }

    /**
     * Dashboard aggregates, scoped to what the caller is allowed to see
     * (employees: their own; finance: everyone's). Powers the UI charts.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // A fresh, correctly-scoped query each time we aggregate.
        $scope = fn () => $user->isFinance()
            ? PaymentRequest::query()
            : PaymentRequest::query()->forUser($user);

        $countByStatus = collect(PaymentStatus::cases())
            ->mapWithKeys(fn (PaymentStatus $s) => [$s->value => 0])
            ->merge($scope()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'))
            ->map(fn ($v) => (int) $v);

        $eurByStatus = collect(PaymentStatus::cases())
            ->mapWithKeys(fn (PaymentStatus $s) => [$s->value => 0.0])
            ->merge($scope()->selectRaw('status, sum(converted_amount_eur) as aggregate')->groupBy('status')->pluck('aggregate', 'status'))
            ->map(fn ($v) => round((float) $v, 2));

        $eurByCurrency = $scope()
            ->selectRaw('currency, sum(converted_amount_eur) as aggregate')
            ->groupBy('currency')
            ->pluck('aggregate', 'currency')
            ->map(fn ($v) => round((float) $v, 2));

        return response()->json([
            'data' => [
                'total_count' => (int) $countByStatus->sum(),
                'total_eur' => round((float) $eurByStatus->sum(), 2),
                'count_by_status' => $countByStatus,
                'eur_by_status' => $eurByStatus,
                'eur_by_currency' => $eurByCurrency,
            ],
        ]);
    }

    /**
     * US-B4 — view a single request (owner or finance only).
     */
    public function show(PaymentRequest $paymentRequest): PaymentRequestResource
    {
        $this->authorize('view', $paymentRequest);

        return new PaymentRequestResource($paymentRequest->load('user'));
    }

    /**
     * US-B5 — finance approves or rejects a pending request. Non-pending
     * requests cannot transition (→ 422 via InvalidStatusTransitionException).
     */
    public function update(UpdatePaymentRequestRequest $request, PaymentRequest $paymentRequest): PaymentRequestResource
    {
        $this->authorize('update', $paymentRequest);

        $paymentRequest->transitionTo(PaymentStatus::from($request->validated('status')));

        return new PaymentRequestResource($paymentRequest->load('user'));
    }

    /**
     * US-B1 — create a payment request, converting the amount to EUR using a
     * rate fetched (and frozen) at creation time.
     */
    public function store(StorePaymentRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $currency = Currency::from($data['currency']);

        // Fetch the rate BEFORE writing anything. If the provider is down this
        // throws (→ 503) and no row is ever persisted (resilience NFR / US-B1).
        $rate = $this->exchangeRates->fetch($currency);

        $paymentRequest = $request->user()->paymentRequests()->create([
            'amount' => $data['amount'],
            'currency' => $currency,
            'exchange_rate' => $rate->rate,
            'rate_source' => $rate->source,
            'rate_fetched_at' => $rate->fetchedAt,
            'converted_amount_eur' => $rate->convertToBase($data['amount']),
            'status' => PaymentStatus::Pending,
            'description' => $data['description'] ?? null,
        ]);

        return PaymentRequestResource::make($paymentRequest)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
