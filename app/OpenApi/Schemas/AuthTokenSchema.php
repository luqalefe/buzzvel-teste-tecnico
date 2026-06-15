<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AuthToken',
    title: 'AuthToken',
    type: 'object',
    properties: [
        new OA\Property(property: 'token', type: 'string', example: '1|abcDEF...plain-text-token'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ],
)]
class AuthTokenSchema {}
