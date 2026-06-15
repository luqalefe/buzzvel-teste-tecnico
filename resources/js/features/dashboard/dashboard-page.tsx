import { CheckCircle2, Clock, ListChecks, Wallet } from 'lucide-react';
import type { ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useStats } from '@/hooks/use-payment-requests';
import { formatEur } from '@/lib/format';
import { useAuth } from '@/providers/auth-provider';
import { PAYMENT_STATUSES, type PaymentStatus } from '@/types';

const STATUS_COLOR: Record<PaymentStatus, string> = {
    pending: 'var(--chart-3)',
    approved: 'var(--chart-2)',
    rejected: 'var(--chart-4)',
    expired: 'var(--chart-5)',
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
    icon: Icon,
}: {
    label: string;
    value: string | number;
    icon: ComponentType<{ className?: string }>;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-5">
                <div className="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-5" />
                </div>
                <div>
                    <div className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</div>
                    <div className="tnum text-2xl font-semibold tracking-tight">{value}</div>
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
        .map(([currency, eur]) => ({ currency, eur }))
        .sort((a, b) => b.eur - a.eur);

    return (
        <div>
            <PageHeader title={t('dashboard.title')} subtitle={subtitle} />

            {stats.total_count === 0 ? (
                <EmptyState title={t('dashboard.empty')} />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label={t('dashboard.totalRequests')} value={stats.total_count} icon={ListChecks} />
                        <StatCard label={t('dashboard.totalEur')} value={formatEur(stats.total_eur, i18n.language)} icon={Wallet} />
                        <StatCard
                            label={t('dashboard.pendingValue')}
                            value={formatEur(stats.eur_by_status.pending ?? 0, i18n.language)}
                            icon={Clock}
                        />
                        <StatCard
                            label={t('dashboard.approvedValue')}
                            value={formatEur(stats.eur_by_status.approved ?? 0, i18n.language)}
                            icon={CheckCircle2}
                        />
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
