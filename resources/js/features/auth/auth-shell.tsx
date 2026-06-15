import type { ReactNode } from 'react';
import { Wallet } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/components/language-switcher';
import { ThemeToggle } from '@/components/theme-toggle';

interface AuthShellProps {
    title: string;
    subtitle: string;
    children: ReactNode;
    footer: ReactNode;
}

export function AuthShell({ title, subtitle, children, footer }: AuthShellProps) {
    const { t } = useTranslation();

    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            {/* Brand panel (desktop) — fixed graphite surface with a coral mark */}
            <aside className="relative hidden flex-col justify-between overflow-hidden bg-panel p-12 text-panel-foreground lg:flex">
                <div className="bg-dotted pointer-events-none absolute inset-0 opacity-[0.1]" />

                <div className="relative flex items-center gap-2.5">
                    <div className="flex size-9 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                        <Wallet className="size-5" />
                    </div>
                    <span className="font-display text-xl font-bold tracking-tight">{t('app.name')}</span>
                </div>

                <div className="relative space-y-8">
                    <h2 className="max-w-sm text-pretty font-display text-3xl font-semibold leading-[1.15] tracking-tight">
                        {t('app.tagline')}
                    </h2>

                    {/* On-theme decoration: the currencies the product speaks — no fake data. */}
                    <div className="flex max-w-xs flex-wrap gap-2">
                        {['EUR', 'USD', 'GBP', 'BRL', 'JPY', 'CHF', 'SEK', 'PLN'].map((code) => (
                            <span
                                key={code}
                                className="tnum rounded-md bg-panel-foreground/10 px-2.5 py-1 text-xs font-medium ring-1 ring-inset ring-panel-foreground/15"
                            >
                                {code}
                            </span>
                        ))}
                    </div>
                </div>

                <div className="relative text-xs text-panel-foreground/55">© {t('app.name')}</div>
            </aside>

            {/* Form side */}
            <main className="relative flex flex-col items-center justify-center bg-background p-6">
                <div className="absolute right-4 top-4 flex items-center gap-1">
                    <LanguageSwitcher />
                    <ThemeToggle />
                </div>

                <div className="mb-7 flex items-center gap-2.5 lg:hidden">
                    <div className="flex size-9 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                        <Wallet className="size-5" />
                    </div>
                    <span className="font-display text-xl font-bold tracking-tight">{t('app.name')}</span>
                </div>

                <div className="w-full max-w-md animate-fade-in-up">
                    <div className="mb-6 space-y-1.5">
                        <h1 className="font-display text-3xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-sm text-muted-foreground">{subtitle}</p>
                    </div>
                    {children}
                    <div className="mt-6 text-center text-sm text-muted-foreground">{footer}</div>
                </div>
            </main>
        </div>
    );
}
