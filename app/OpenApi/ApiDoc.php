<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Multi-Currency Payment API',
    description: 'Employees submit payment requests in their local currency; the system freezes the EUR exchange rate at creation, converts to EUR, and lets finance approve or reject. Pending requests expire automatically after 48h.',
)]
#[OA\Server(url: '/api', description: 'API base path')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum personal access token',
    description: 'Send the token returned by /register or /login as: Authorization: Bearer {token}',
)]
#[OA\Tag(name: 'Auth', description: 'Registration, login and logout')]
#[OA\Tag(name: 'Payment Requests', description: 'Create, list, view and decide payment requests')]
class ApiDoc
{
    #[OA\Post(
        path: '/register',
        tags: ['Auth'],
        summary: 'Register a new employee and receive an access token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'country', 'currency'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Alice Employee'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'country', type: 'string', example: 'Brazil'),
                    new OA\Property(property: 'currency', type: 'string', example: 'BRL'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registered', content: new OA\JsonContent(ref: '#/components/schemas/AuthToken')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/login',
        tags: ['Auth'],
        summary: 'Authenticate and receive an access token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'finance@buzzvel.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated', content: new OA\JsonContent(ref: '#/components/schemas/AuthToken')),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function login(): void {}

    #[OA\Post(
        path: '/logout',
        tags: ['Auth'],
        summary: 'Revoke the current access token',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function logout(): void {}

    #[OA\Get(
        path: '/user',
        tags: ['Auth'],
        summary: 'The currently authenticated user',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current user',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function user(): void {}

    #[OA\Get(
        path: '/payment-requests',
        tags: ['Payment Requests'],
        summary: 'List payment requests (own for employees, all for finance)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected', 'expired'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaymentRequest')),
                    new OA\Property(property: 'links', type: 'object'),
                    new OA\Property(property: 'meta', type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Invalid filter', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function index(): void {}

    #[OA\Get(
        path: '/payment-requests/preview',
        tags: ['Payment Requests'],
        summary: 'Preview an EUR conversion without persisting anything',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'amount', in: 'query', required: true, schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'currency', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'BRL')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Conversion preview',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'currency', type: 'string', example: 'BRL'),
                        new OA\Property(property: 'exchange_rate', type: 'number', format: 'float', example: 5.5),
                        new OA\Property(property: 'rate_source', type: 'string', example: 'exchangerate-api.com'),
                        new OA\Property(property: 'converted_amount_eur', type: 'number', format: 'float', example: 20.0),
                    ]),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 503, description: 'Exchange-rate provider unavailable', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function preview(): void {}

    #[OA\Get(
        path: '/payment-requests/stats',
        tags: ['Payment Requests'],
        summary: 'Dashboard aggregates (scoped: own for employees, all for finance)',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Aggregates',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'total_count', type: 'integer', example: 7),
                        new OA\Property(property: 'total_eur', type: 'number', format: 'float', example: 1234.56),
                        new OA\Property(property: 'count_by_status', type: 'object', example: ['pending' => 3, 'approved' => 2, 'rejected' => 1, 'expired' => 1]),
                        new OA\Property(property: 'eur_by_status', type: 'object'),
                        new OA\Property(property: 'eur_by_currency', type: 'object'),
                    ]),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function stats(): void {}

    #[OA\Post(
        path: '/payment-requests',
        tags: ['Payment Requests'],
        summary: 'Create a payment request with automatic EUR conversion',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'currency'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 110.0),
                    new OA\Property(property: 'currency', type: 'string', example: 'BRL'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Conference travel'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentRequest')])),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 503, description: 'Exchange-rate provider unavailable', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/payment-requests/{paymentRequest}',
        tags: ['Payment Requests'],
        summary: 'View a single payment request (owner or finance)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentRequest')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function show(): void {}

    #[OA\Patch(
        path: '/payment-requests/{paymentRequest}',
        tags: ['Payment Requests'],
        summary: 'Approve or reject a pending request (finance only)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected'], example: 'approved'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentRequest')])),
            new OA\Response(response: 403, description: 'Forbidden (not finance)', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 422, description: 'Invalid status or transition', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function update(): void {}
}
