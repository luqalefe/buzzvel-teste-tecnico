<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A stored response keyed by a client-supplied Idempotency-Key (+ user), so a
 * retried request replays the original result instead of acting twice.
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'request_hash',
        'response_status',
        'response_body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
        ];
    }
}
