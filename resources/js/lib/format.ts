/**
 * Each supported UI language mapped to a native BCP-47 locale, so money, dates
 * and numbers format in local conventions (e.g. German "1.234,56 €", not US).
 */
const INTL_LOCALES: Record<string, string> = {
    bg: 'bg-BG', cs: 'cs-CZ', da: 'da-DK', de: 'de-DE', el: 'el-GR',
    en: 'en-US', es: 'es-ES', et: 'et-EE', fi: 'fi-FI', fr: 'fr-FR',
    ga: 'ga-IE', hr: 'hr-HR', hu: 'hu-HU', it: 'it-IT', lt: 'lt-LT',
    lv: 'lv-LV', mt: 'mt-MT', nl: 'nl-NL', pl: 'pl-PL', pt: 'pt-PT',
    ro: 'ro-RO', sk: 'sk-SK', sl: 'sl-SI', sv: 'sv-SE',
};

/** Map an app language (e.g. "de", "pt-BR") to a full BCP-47 locale for Intl. */
export function intlLocale(language: string): string {
    return INTL_LOCALES[language.split('-')[0]] ?? 'en-US';
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
