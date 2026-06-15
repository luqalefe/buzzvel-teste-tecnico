export type Role = 'employee' | 'finance';

export type PaymentStatus = 'pending' | 'approved' | 'rejected' | 'expired';

export interface User {
    id: number;
    name: string;
    email: string;
    country: string;
    currency: string;
    role: Role;
    created_at: string | null;
    updated_at: string | null;
}

export interface PaymentRequest {
    id: number;
    amount: number;
    currency: string;
    exchange_rate: number;
    rate_source: string;
    rate_fetched_at: string | null;
    converted_amount_eur: number;
    status: PaymentStatus;
    description: string | null;
    expires_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    user?: User | null;
}

export interface AuthResponse {
    token: string;
    token_type: string;
    user: User;
}

export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: PageMeta;
}

export interface Stats {
    total_count: number;
    total_eur: number;
    count_by_status: Record<PaymentStatus, number>;
    eur_by_status: Record<PaymentStatus, number>;
    eur_by_currency: Record<string, number>;
}

export const CURRENCIES = [
    'ALL', 'AUD', 'BAM', 'BGN', 'BRL', 'BYN', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK',
    'EUR', 'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MDL',
    'MKD', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RSD', 'RUB', 'SEK',
    'SGD', 'THB', 'TRY', 'UAH', 'USD', 'ZAR',
] as const;

export const PAYMENT_STATUSES: PaymentStatus[] = ['pending', 'approved', 'rejected', 'expired'];
