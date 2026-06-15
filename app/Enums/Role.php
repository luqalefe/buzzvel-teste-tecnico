<?php

namespace App\Enums;

/**
 * Application roles.
 *
 * - Employee: can create, list and view their own payment requests.
 * - Finance:  everything an employee can do, plus approve/reject pending requests.
 */
enum Role: string
{
    case Employee = 'employee';
    case Finance = 'finance';

    public function isFinance(): bool
    {
        return $this === self::Finance;
    }
}
