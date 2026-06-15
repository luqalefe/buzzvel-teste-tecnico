import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';
import { Countdown } from '@/components/countdown';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { usePaymentRequest } from '@/hooks/use-payment-requests';
import { formatDateTime, formatEur, formatNumber } from '@/lib/format';
import { useAuth } from '@/providers/auth-provider';
import { DecisionButtons } from './decision-buttons';

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-2.5">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-right text-sm font-medium">{children}</span>
        </div>
    );
}

export function PaymentRequestDetailPage() {
    const { id } = useParams();
    const { t, i18n } = useTranslation();
    const { user } = useAuth();
    const navigate = useNavigate();
    const { data: request, isLoading, isError } = usePaymentRequest(id);
    const isFinance = user?.role === 'finance';

    return (
        <div>
            <Button variant="ghost" size="sm" className="mb-4 -ml-2" onClick={() => navigate(-1)}>
                <ArrowLeft className="size-4" />
                {t('common.back')}
            </Button>

            {isLoading ? (
                <Skeleton className="h-96 w-full max-w-2xl rounded-xl" />
            ) : isError || !request ? (
                <EmptyState title={t('errors.notFound')} />
            ) : (
                <Card className="max-w-2xl">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-2">
                            <CardTitle className="text-xl">{t('requests.detailTitle', { id: request.id })}</CardTitle>
                            <div className="flex items-center gap-2">
                                <StatusBadge status={request.status} />
                                {request.status === 'pending' && (
                                    <Countdown expiresAt={request.expires_at} className="text-xs" />
                                )}
                            </div>
                        </div>
                        <div className="rounded-xl border border-brand/20 bg-brand/5 px-4 py-2.5 text-right">
                            <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {t('requests.convertedAmount')}
                            </div>
                            <div className="tnum text-2xl font-bold text-foreground">
                                {formatEur(request.converted_amount_eur, i18n.language)}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="divide-y">
                            <Row label={t('requests.originalAmount')}>
                                <span className="tnum">{formatNumber(request.amount, i18n.language, 2)}</span>{' '}
                                {request.currency}
                            </Row>
                            <Row label={t('requests.rate')}>
                                <span className="tnum">{formatNumber(request.exchange_rate, i18n.language, 6)}</span>
                            </Row>
                            <Row label={t('requests.rateSource')}>{request.rate_source}</Row>
                            <Row label={t('requests.rateFetchedAt')}>
                                {formatDateTime(request.rate_fetched_at, i18n.language)}
                            </Row>
                            {isFinance && request.user && (
                                <Row label={t('requests.requester')}>{request.user.name}</Row>
                            )}
                            <Row label={t('requests.created')}>
                                {formatDateTime(request.created_at, i18n.language)}
                            </Row>
                            {request.status === 'pending' && (
                                <Row label={t('requests.expires')}>
                                    {formatDateTime(request.expires_at, i18n.language)}
                                </Row>
                            )}
                        </div>

                        <Separator className="my-4" />

                        <div>
                            <div className="mb-1 text-sm text-muted-foreground">{t('requests.description')}</div>
                            <p className="text-sm">
                                {request.description || (
                                    <span className="text-muted-foreground">{t('requests.noDescription')}</span>
                                )}
                            </p>
                        </div>

                        {isFinance && request.status === 'pending' && (
                            <>
                                <Separator className="my-4" />
                                <DecisionButtons request={request} size="default" />
                            </>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
