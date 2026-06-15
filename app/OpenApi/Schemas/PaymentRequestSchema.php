<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PaymentRequest',
    title: 'PaymentRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 110.0, description: 'Original amount in the local currency'),
        new OA\Property(property: 'currency', type: 'string', example: 'BRL'),
        new OA\Property(property: 'exchange_rate', type: 'number', format: 'float', example: 5.5, description: 'EUR → local rate, frozen at creation'),
        new OA\Property(property: 'rate_source', type: 'string', example: 'exchangerate-api.com', description: "'system' for EUR (no external call)"),
        new OA\Property(property: 'rate_fetched_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'converted_amount_eur', type: 'number', format: 'float', example: 20.0),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected', 'expired'], example: 'pending'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Conference travel'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true, description: '48h after creation while pending; null otherwise'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'user', ref: '#/components/schemas/User', nullable: true),
    ],
)]
class PaymentRequestSchema {}
