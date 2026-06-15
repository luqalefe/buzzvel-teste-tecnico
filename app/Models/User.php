<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country',
        'currency',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'currency' => Currency::class,
            'role' => Role::class,
        ];
    }

    /**
     * The payment requests submitted by this user.
     *
     * @return HasMany<PaymentRequest, $this>
     */
    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    /**
     * Whether this user belongs to the finance team.
     */
    public function isFinance(): bool
    {
        return $this->role === Role::Finance;
    }
}
