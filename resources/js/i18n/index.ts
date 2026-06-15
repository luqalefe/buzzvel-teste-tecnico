import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';
import { LANGUAGE_CODES } from './languages';
import { bg } from './locales/bg';
import { cs } from './locales/cs';
import { da } from './locales/da';
import { de } from './locales/de';
import { el } from './locales/el';
import { en } from './locales/en';
import { es } from './locales/es';
import { et } from './locales/et';
import { fi } from './locales/fi';
import { fr } from './locales/fr';
import { ga } from './locales/ga';
import { hr } from './locales/hr';
import { hu } from './locales/hu';
import { it } from './locales/it';
import { lt } from './locales/lt';
import { lv } from './locales/lv';
import { mt } from './locales/mt';
import { nl } from './locales/nl';
import { pl } from './locales/pl';
import { pt } from './locales/pt';
import { ro } from './locales/ro';
import { sk } from './locales/sk';
import { sl } from './locales/sl';
import { sv } from './locales/sv';

const resources = {
    bg: { translation: bg },
    cs: { translation: cs },
    da: { translation: da },
    de: { translation: de },
    el: { translation: el },
    en: { translation: en },
    es: { translation: es },
    et: { translation: et },
    fi: { translation: fi },
    fr: { translation: fr },
    ga: { translation: ga },
    hr: { translation: hr },
    hu: { translation: hu },
    it: { translation: it },
    lt: { translation: lt },
    lv: { translation: lv },
    mt: { translation: mt },
    nl: { translation: nl },
    pl: { translation: pl },
    pt: { translation: pt },
    ro: { translation: ro },
    sk: { translation: sk },
    sl: { translation: sl },
    sv: { translation: sv },
};

void i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources,
        fallbackLng: 'en',
        supportedLngs: LANGUAGE_CODES,
        load: 'languageOnly',
        interpolation: { escapeValue: false },
        detection: {
            order: ['localStorage', 'navigator'],
            lookupLocalStorage: 'language',
            caches: ['localStorage'],
        },
    });

export default i18n;
