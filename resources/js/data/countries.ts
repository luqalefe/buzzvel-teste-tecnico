/**
 * European countries with their ISO 3166-1 alpha-2 code, primary currency
 * (ISO 4217), English demonym/nationality, and primary official language
 * (ISO 639-1 — used to suggest the UI language at registration).
 *
 * Sources: EU Interinstitutional Style Guide (countries/currencies) and
 * Wikipedia's list of demonyms. Currencies are all supported by the provider.
 */
export interface Country {
    code: string;
    name: string;
    currency: string;
    nationality: string;
    lang: string;
}

export const EUROPEAN_COUNTRIES: Country[] = [
    { code: 'AL', name: 'Albania', currency: 'ALL', nationality: 'Albanian', lang: 'sq' },
    { code: 'AD', name: 'Andorra', currency: 'EUR', nationality: 'Andorran', lang: 'ca' },
    { code: 'AT', name: 'Austria', currency: 'EUR', nationality: 'Austrian', lang: 'de' },
    { code: 'BY', name: 'Belarus', currency: 'BYN', nationality: 'Belarusian', lang: 'be' },
    { code: 'BE', name: 'Belgium', currency: 'EUR', nationality: 'Belgian', lang: 'nl' },
    { code: 'BA', name: 'Bosnia and Herzegovina', currency: 'BAM', nationality: 'Bosnian', lang: 'bs' },
    { code: 'BG', name: 'Bulgaria', currency: 'BGN', nationality: 'Bulgarian', lang: 'bg' },
    { code: 'HR', name: 'Croatia', currency: 'EUR', nationality: 'Croatian', lang: 'hr' },
    { code: 'CY', name: 'Cyprus', currency: 'EUR', nationality: 'Cypriot', lang: 'el' },
    { code: 'CZ', name: 'Czechia', currency: 'CZK', nationality: 'Czech', lang: 'cs' },
    { code: 'DK', name: 'Denmark', currency: 'DKK', nationality: 'Danish', lang: 'da' },
    { code: 'EE', name: 'Estonia', currency: 'EUR', nationality: 'Estonian', lang: 'et' },
    { code: 'FI', name: 'Finland', currency: 'EUR', nationality: 'Finnish', lang: 'fi' },
    { code: 'FR', name: 'France', currency: 'EUR', nationality: 'French', lang: 'fr' },
    { code: 'DE', name: 'Germany', currency: 'EUR', nationality: 'German', lang: 'de' },
    { code: 'GR', name: 'Greece', currency: 'EUR', nationality: 'Greek', lang: 'el' },
    { code: 'HU', name: 'Hungary', currency: 'HUF', nationality: 'Hungarian', lang: 'hu' },
    { code: 'IS', name: 'Iceland', currency: 'ISK', nationality: 'Icelandic', lang: 'is' },
    { code: 'IE', name: 'Ireland', currency: 'EUR', nationality: 'Irish', lang: 'en' },
    { code: 'IT', name: 'Italy', currency: 'EUR', nationality: 'Italian', lang: 'it' },
    { code: 'XK', name: 'Kosovo', currency: 'EUR', nationality: 'Kosovar', lang: 'sq' },
    { code: 'LV', name: 'Latvia', currency: 'EUR', nationality: 'Latvian', lang: 'lv' },
    { code: 'LI', name: 'Liechtenstein', currency: 'CHF', nationality: 'Liechtensteiner', lang: 'de' },
    { code: 'LT', name: 'Lithuania', currency: 'EUR', nationality: 'Lithuanian', lang: 'lt' },
    { code: 'LU', name: 'Luxembourg', currency: 'EUR', nationality: 'Luxembourgish', lang: 'fr' },
    { code: 'MT', name: 'Malta', currency: 'EUR', nationality: 'Maltese', lang: 'mt' },
    { code: 'MD', name: 'Moldova', currency: 'MDL', nationality: 'Moldovan', lang: 'ro' },
    { code: 'MC', name: 'Monaco', currency: 'EUR', nationality: 'Monégasque', lang: 'fr' },
    { code: 'ME', name: 'Montenegro', currency: 'EUR', nationality: 'Montenegrin', lang: 'sr' },
    { code: 'NL', name: 'Netherlands', currency: 'EUR', nationality: 'Dutch', lang: 'nl' },
    { code: 'MK', name: 'North Macedonia', currency: 'MKD', nationality: 'Macedonian', lang: 'mk' },
    { code: 'NO', name: 'Norway', currency: 'NOK', nationality: 'Norwegian', lang: 'no' },
    { code: 'PL', name: 'Poland', currency: 'PLN', nationality: 'Polish', lang: 'pl' },
    { code: 'PT', name: 'Portugal', currency: 'EUR', nationality: 'Portuguese', lang: 'pt' },
    { code: 'RO', name: 'Romania', currency: 'RON', nationality: 'Romanian', lang: 'ro' },
    { code: 'RU', name: 'Russia', currency: 'RUB', nationality: 'Russian', lang: 'ru' },
    { code: 'SM', name: 'San Marino', currency: 'EUR', nationality: 'Sammarinese', lang: 'it' },
    { code: 'RS', name: 'Serbia', currency: 'RSD', nationality: 'Serbian', lang: 'sr' },
    { code: 'SK', name: 'Slovakia', currency: 'EUR', nationality: 'Slovak', lang: 'sk' },
    { code: 'SI', name: 'Slovenia', currency: 'EUR', nationality: 'Slovenian', lang: 'sl' },
    { code: 'ES', name: 'Spain', currency: 'EUR', nationality: 'Spanish', lang: 'es' },
    { code: 'SE', name: 'Sweden', currency: 'SEK', nationality: 'Swedish', lang: 'sv' },
    { code: 'CH', name: 'Switzerland', currency: 'CHF', nationality: 'Swiss', lang: 'de' },
    { code: 'TR', name: 'Türkiye', currency: 'TRY', nationality: 'Turkish', lang: 'tr' },
    { code: 'UA', name: 'Ukraine', currency: 'UAH', nationality: 'Ukrainian', lang: 'uk' },
    { code: 'GB', name: 'United Kingdom', currency: 'GBP', nationality: 'British', lang: 'en' },
    { code: 'VA', name: 'Vatican City', currency: 'EUR', nationality: 'Vatican', lang: 'it' },
];

export const COUNTRY_BY_NAME = new Map(EUROPEAN_COUNTRIES.map((c) => [c.name, c]));
