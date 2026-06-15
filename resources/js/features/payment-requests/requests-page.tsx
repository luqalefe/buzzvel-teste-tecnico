import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePaymentRequests } from '@/hooks/use-payment-requests';
import { useAuth } from '@/providers/auth-provider';
import { PAYMENT_STATUSES, type PaymentStatus } from '@/types';
import { CreateRequestDialog } from './create-dialog';
import { RequestsTable } from './requests-table';

type Filter = PaymentStatus | 'all';

export function RequestsPage() {
    const { t } = useTranslation();
    const { user } = useAuth();
    const isFinance = user?.role === 'finance';

    const [status, setStatus] = useState<Filter>('all');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError } = usePaymentRequests({ status, page });
    const rows = data?.data ?? [];

    const onStatusChange = (value: string) => {
        setStatus(value as Filter);
        setPage(1);
    };

    return (
        <div>
            <PageHeader
                title={isFinance ? t('requests.titleFinance') : t('requests.title')}
                subtitle={isFinance ? t('requests.subtitleFinance') : t('requests.subtitle')}
                actions={<CreateRequestDialog />}
            />

            <Tabs value={status} onValueChange={onStatusChange} className="mb-4">
                <TabsList className="flex-wrap">
                    <TabsTrigger value="all">{t('common.all')}</TabsTrigger>
                    {PAYMENT_STATUSES.map((s) => (
                        <TabsTrigger key={s} value={s}>
                            {t(`status.${s}`)}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </Tabs>

            {/* key on the filter so the results fade in when it changes */}
            <div key={status} className="animate-fade-in">
                {isError ? (
                    <EmptyState title={t('errors.loadFailed')} />
                ) : !isLoading && rows.length === 0 ? (
                    <EmptyState title={status === 'all' ? t('requests.empty') : t('requests.emptyFiltered')}>
                        {status === 'all' && <CreateRequestDialog />}
                    </EmptyState>
                ) : (
                    <>
                        <RequestsTable
                            requests={rows}
                            isLoading={isLoading}
                            showRequester={isFinance}
                            showDecisions={isFinance}
                        />
                        <Pagination meta={data?.meta} page={page} onPage={setPage} />
                    </>
                )}
            </div>
        </div>
    );
}
