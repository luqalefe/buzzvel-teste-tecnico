<?php

namespace App\Http\Controllers\Api;

use App\Actions\SummarisePaymentRequests;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest\IndexPaymentRequestRequest;
use App\Http\Requests\PaymentRequest\PreviewPaymentRequestRequest;
use App\Http\Requests\PaymentRequest\StorePaymentRequestRequest;
use App\Http\Requests\PaymentRequest\UpdatePaymentRequestRequest;
use App\Http\Resources\PaymentRequestResource;
use App\Models\PaymentRequest;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PaymentRequestController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly SummarisePaymentRequests $summarise,
    ) {}

    /**
     * US-B3 — list payment requests. Employees see only their own; finance sees
     * everyone's. Optionally filtered by status.
     */
    public function index(IndexPaymentRequestRequest $request): AnonymousResourceCollection
    {
        $query = PaymentRequest::query()
            ->with('user')
            ->visibleTo($request->user())
            ->latest();

        if ($status = $request->validated('status')) {
            $query->withStatus(PaymentStatus::from($status));
        }

        $perPage = (int) ($request->validated('per_page') ?? 15);

        return PaymentRequestResource::collection($query->paginate($perPage)->withQueryString());
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
     * Preview the EUR conversion for an amount/currency without persisting
     * anything — powers the live preview in the "new request" form.
     */
    public function preview(PreviewPaymentRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
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
     * Dashboard aggregates, scoped to what the caller may see. Powers the charts.
     */
    public function stats(Request $request): JsonResponse
    {
        return response()->json(['data' => ($this->summarise)($request->user())]);
    }
}
