<?php

namespace App\Enums;

/**
 * Currencies supported by the system — every European national currency plus a
 * handful of common global ones. All are fetchable (EUR base) from the
 * configured provider (exchangerate-api.com), so a currency is only "valid" if
 * we can obtain an EUR conversion for it.
 */
enum Currency: string
{
    case ALL = 'ALL'; // Albanian lek
    case AUD = 'AUD';
    case BAM = 'BAM'; // Bosnia and Herzegovina convertible mark
    case BGN = 'BGN';
    case BRL = 'BRL';
    case BYN = 'BYN'; // Belarusian ruble
    case CAD = 'CAD';
    case CHF = 'CHF';
    case CNY = 'CNY';
    case CZK = 'CZK';
    case DKK = 'DKK';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case HKD = 'HKD';
    case HUF = 'HUF';
    case IDR = 'IDR';
    case ILS = 'ILS';
    case INR = 'INR';
    case ISK = 'ISK';
    case JPY = 'JPY';
    case KRW = 'KRW';
    case MDL = 'MDL'; // Moldovan leu
    case MKD = 'MKD'; // North Macedonian denar
    case MXN = 'MXN';
    case MYR = 'MYR';
    case NOK = 'NOK';
    case NZD = 'NZD';
    case PHP = 'PHP';
    case PLN = 'PLN';
    case RON = 'RON';
    case RSD = 'RSD'; // Serbian dinar
    case RUB = 'RUB'; // Russian ruble
    case SEK = 'SEK';
    case SGD = 'SGD';
    case THB = 'THB';
    case TRY = 'TRY';
    case UAH = 'UAH'; // Ukrainian hryvnia
    case USD = 'USD';
    case ZAR = 'ZAR';

    /**
     * The base currency every request is converted into.
     */
    public const BASE = self::EUR;

    public function isBase(): bool
    {
        return $this === self::EUR;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
