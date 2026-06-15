/** The 24 official languages of the European Union (ISO 639-1 + native name). */
export interface LanguageMeta {
    code: string;
    native: string;
    english: string;
}

export const LANGUAGES: LanguageMeta[] = [
    { code: 'bg', native: 'Български', english: 'Bulgarian' },
    { code: 'cs', native: 'Čeština', english: 'Czech' },
    { code: 'da', native: 'Dansk', english: 'Danish' },
    { code: 'de', native: 'Deutsch', english: 'German' },
    { code: 'el', native: 'Ελληνικά', english: 'Greek' },
    { code: 'en', native: 'English', english: 'English' },
    { code: 'es', native: 'Español', english: 'Spanish' },
    { code: 'et', native: 'Eesti', english: 'Estonian' },
    { code: 'fi', native: 'Suomi', english: 'Finnish' },
    { code: 'fr', native: 'Français', english: 'French' },
    { code: 'ga', native: 'Gaeilge', english: 'Irish' },
    { code: 'hr', native: 'Hrvatski', english: 'Croatian' },
    { code: 'hu', native: 'Magyar', english: 'Hungarian' },
    { code: 'it', native: 'Italiano', english: 'Italian' },
    { code: 'lt', native: 'Lietuvių', english: 'Lithuanian' },
    { code: 'lv', native: 'Latviešu', english: 'Latvian' },
    { code: 'mt', native: 'Malti', english: 'Maltese' },
    { code: 'nl', native: 'Nederlands', english: 'Dutch' },
    { code: 'pl', native: 'Polski', english: 'Polish' },
    { code: 'pt', native: 'Português', english: 'Portuguese' },
    { code: 'ro', native: 'Română', english: 'Romanian' },
    { code: 'sk', native: 'Slovenčina', english: 'Slovak' },
    { code: 'sl', native: 'Slovenščina', english: 'Slovenian' },
    { code: 'sv', native: 'Svenska', english: 'Swedish' },
];

export const LANGUAGE_CODES = LANGUAGES.map((l) => l.code);
