import { CheckSquare, FileText, LayoutDashboard, ListChecks, Wallet } from 'lucide-react';
import type { ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink, Outlet } from 'react-router-dom';
import { LanguageSwitcher } from '@/components/language-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { UserMenu } from '@/components/user-menu';
import { useAuth } from '@/providers/auth-provider';
import { cn } from '@/lib/utils';

interface NavItem {
    to: string;
    label: string;
    Icon: ComponentType<{ className?: string }>;
}

export function AppLayout() {
    const { user } = useAuth();
    const { t } = useTranslation();
    const isFinance = user?.role === 'finance';

    const nav: NavItem[] = [
        { to: '/dashboard', label: t('nav.dashboard'), Icon: LayoutDashboard },
        { to: '/requests', label: isFinance ? t('nav.allRequests') : t('nav.requests'), Icon: ListChecks },
        ...(isFinance ? [{ to: '/approvals', label: t('nav.approvals'), Icon: CheckSquare }] : []),
    ];

    const linkClass = ({ isActive }: { isActive: boolean }) =>
        cn(
            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
            isActive ? 'bg-brand/10 text-brand' : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
        );

    return (
        <div className="flex min-h-dvh bg-background">
            {/* Sidebar (desktop) */}
            <aside className="hidden w-64 shrink-0 flex-col border-r bg-card md:flex">
                <div className="flex h-16 items-center gap-2.5 border-b px-6">
                    <div className="flex size-8 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                        <Wallet className="size-4" />
                    </div>
                    <div>
                        <div className="font-display text-base font-bold leading-tight tracking-tight">{t('app.name')}</div>
                        <div className="text-xs text-muted-foreground">{t('app.tagline')}</div>
                    </div>
                </div>
                <nav className="flex flex-1 flex-col gap-1 p-3">
                    {nav.map(({ to, label, Icon }) => (
                        <NavLink key={to} to={to} className={linkClass}>
                            <Icon className="size-4" />
                            {label}
                        </NavLink>
                    ))}
                </nav>
                <div className="border-t p-3">
                    <a
                        href="/api/documentation"
                        target="_blank"
                        rel="noreferrer"
                        className="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        <FileText className="size-4" />
                        {t('nav.documentation')}
                    </a>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 flex h-16 items-center gap-2 border-b bg-background/80 px-4 backdrop-blur md:px-8">
                    {/* Mobile nav */}
                    <nav className="flex items-center gap-1 overflow-x-auto md:hidden">
                        {nav.map(({ to, label, Icon }) => (
                            <NavLink
                                key={to}
                                to={to}
                                className={({ isActive }) =>
                                    cn(
                                        'flex items-center gap-1.5 whitespace-nowrap rounded-md px-2.5 py-1.5 text-xs font-medium',
                                        isActive ? 'bg-brand/10 text-brand' : 'text-muted-foreground',
                                    )
                                }
                            >
                                <Icon className="size-4" />
                                {label}
                            </NavLink>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-1">
                        <LanguageSwitcher />
                        <ThemeToggle />
                        <UserMenu />
                    </div>
                </header>

                <main className="flex-1 p-4 md:p-8">
                    <div className="mx-auto w-full max-w-6xl animate-fade-in-up">
                        <Outlet />
                    </div>
                </main>
            </div>
        </div>
    );
}
