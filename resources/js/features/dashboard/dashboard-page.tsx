import { CheckCircle2, Clock, ListChecks, Wallet } from 'lucide-react';
import type { ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useCountUp } from '@/hooks/use-count-up';
import { useStats } from '@/hooks/use-payment-requests';
import { formatEur, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useAuth } from '@/providers/auth-provider';
import { PAYMENT_STATUSES, type PaymentStatus } from '@/types';

const STATUS_COLOR: Record<PaymentStatus, string> = {
    pending: 'var(--status-pending)',
    approved: 'var(--status-approved)',
    rejected: 'var(--status-rejected)',
    expired: 'var(--status-expired)',
};

const tooltipStyle = {
    background: 'var(--popover)',
    border: '1px solid var(--border)',
    borderRadius: 8,
    color: 'var(--popover-foreground)',
    fontSize: 12,
} as const;

const axisTick = { fill: 'var(--muted-foreground)', fontSize: 12 } as const;

function StatCard({
    label,
    value,
    format,
    icon: Icon,
    highlight,
}: {
    label: string;
    value: number;
    format: (n: number) => string;
    icon: ComponentType<{ className?: string }>;
    highlight?: boolean;
}) {
    const animated = useCountUp(value);

    return (
        <Card className={cn(highlight && 'ring-1 ring-brand/30')}>
            <CardContent className="flex items-center gap-4 p-5">
                <div
                    className={cn(
                        'flex size-11 items-center justify-center rounded-lg',
                        highlight ? 'bg-brand/10 text-brand' : 'bg-muted text-muted-foreground',
                    )}
                >
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0">
                    <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</div>
                    <div className="tnum truncate text-2xl font-semibold tracking-tight">{format(animated)}</div>
                </div>
            </CardContent>
        </Card>
    );
}

export function DashboardPage() {
    const { t, i18n } = useTranslation();
    const { user } = useAuth();
    const { data: stats, isLoading, isError } = useStats();
    const isFinance = user?.role === 'finance';

    const subtitle = isFinance ? t('dashboard.subtitleFinance') : t('dashboard.subtitle');
    const eur = (n: number) => formatEur(n, i18n.language);
    const count = (n: number) => formatNumber(Math.round(n), i18n.language, 0);

    if (isLoading) {
        return (
            <div>
                <PageHeader title={t('dashboard.title')} subtitle={subtitle} />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <Skeleton key={i} className="h-24 rounded-xl" />
                    ))}
                </div>
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    <Skeleton className="h-80 rounded-xl" />
                    <Skeleton className="h-80 rounded-xl" />
                </div>
            </div>
        );
    }

    if (isError || !stats) {
        return (
            <div>
                <PageHeader title={t('dashboard.title')} subtitle={subtitle} />
                <EmptyState title={t('errors.loadFailed')} />
            </div>
        );
    }

    const statusData = PAYMENT_STATUSES.map((s) => ({
        key: s,
        label: t(`status.${s}`),
        count: stats.count_by_status[s] ?? 0,
    }));

    const currencyData = Object.entries(stats.eur_by_currency)
        .map(([currency, value]) => ({ currency, eur: value }))
        .sort((a, b) => b.eur - a.eur);

    return (
        <div>
            <PageHeader title={t('dashboard.title')} subtitle={subtitle} />

            {stats.total_count === 0 ? (
                <EmptyState title={t('dashboard.empty')} />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label={t('dashboard.totalRequests')} value={stats.total_count} format={count} icon={ListChecks} />
                        <StatCard label={t('dashboard.totalEur')} value={stats.total_eur} format={eur} icon={Wallet} highlight />
                        <StatCard label={t('dashboard.pendingValue')} value={stats.eur_by_status.pending ?? 0} format={eur} icon={Clock} />
                        <StatCard label={t('dashboard.approvedValue')} value={stats.eur_by_status.approved ?? 0} format={eur} icon={CheckCircle2} />
                    </div>

                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('dashboard.byStatus')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={statusData} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                                            <XAxis dataKey="label" tick={axisTick} tickLine={false} axisLine={false} />
                                            <YAxis allowDecimals={false} tick={axisTick} tickLine={false} axisLine={false} />
                                            <Tooltip contentStyle={tooltipStyle} cursor={{ fill: 'var(--accent)' }} />
                                            <Bar dataKey="count" radius={[6, 6, 0, 0]}>
                                                {statusData.map((d) => (
                                                    <Cell key={d.key} fill={STATUS_COLOR[d.key]} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('dashboard.eurByCurrency')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={currencyData} margin={{ top: 8, right: 8, bottom: 0, left: -8 }}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                                            <XAxis dataKey="currency" tick={axisTick} tickLine={false} axisLine={false} />
                                            <YAxis tick={axisTick} tickLine={false} axisLine={false} />
                                            <Tooltip
                                                contentStyle={tooltipStyle}
                                                cursor={{ fill: 'var(--accent)' }}
                                                formatter={(value) => formatEur(Number(value), i18n.language)}
                                            />
                                            <Bar dataKey="eur" radius={[6, 6, 0, 0]} fill="var(--chart-1)" />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </>
            )}
        </div>
    );
}
