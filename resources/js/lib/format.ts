/** Map an app language to a full BCP-47 locale used for Intl formatting. */
export function intlLocale(language: string): string {
    return language.startsWith('pt') ? 'pt-BR' : 'en-US';
}

export function formatMoney(value: number, currency: string, language: string): string {
    try {
        return new Intl.NumberFormat(intlLocale(language), { style: 'currency', currency }).format(value);
    } catch {
        return `${value.toFixed(2)} ${currency}`;
    }
}

export function formatEur(value: number, language: string): string {
    return formatMoney(value, 'EUR', language);
}

export function formatNumber(value: number, language: string, digits = 4): string {
    return new Intl.NumberFormat(intlLocale(language), { maximumFractionDigits: digits }).format(value);
}

export function formatDateTime(iso: string | null, language: string): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(intlLocale(language), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

export function formatDate(iso: string | null, language: string): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(intlLocale(language), { dateStyle: 'medium' }).format(new Date(iso));
}
