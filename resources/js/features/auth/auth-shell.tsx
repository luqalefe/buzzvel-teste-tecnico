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

                    <div className="max-w-xs rounded-xl bg-panel-foreground/10 p-5 ring-1 ring-inset ring-panel-foreground/15 backdrop-blur">
                        <div className="text-xs uppercase tracking-wider text-panel-foreground/70">
                            {t('dashboard.totalEur')}
                        </div>
                        <div className="tnum mt-1.5 text-3xl font-semibold">€34.119,32</div>
                        <div className="mt-3 flex items-end gap-1">
                            {[24, 40, 32, 56, 72, 64, 88].map((h, i) => (
                                <span
                                    key={i}
                                    className="w-3 rounded-sm bg-panel-foreground/35"
                                    style={{ height: `${h}%` }}
                                />
                            ))}
                        </div>
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
