import { ClipboardCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { RequestsTable } from '@/features/payment-requests/requests-table';
import { usePaymentRequests } from '@/hooks/use-payment-requests';

export function ApprovalsPage() {
    const { t } = useTranslation();
    const [page, setPage] = useState(1);
    const { data, isLoading, isError } = usePaymentRequests({ status: 'pending', page });
    const rows = data?.data ?? [];

    return (
        <div>
            <PageHeader title={t('nav.approvals')} subtitle={t('requests.subtitleFinance')} />

            {isError ? (
                <EmptyState title={t('errors.loadFailed')} />
            ) : !isLoading && rows.length === 0 ? (
                <EmptyState icon={ClipboardCheck} title={t('requests.emptyFiltered')} />
            ) : (
                <>
                    <RequestsTable requests={rows} isLoading={isLoading} showRequester showDecisions />
                    <Pagination meta={data?.meta} page={page} onPage={setPage} />
                </>
            )}
        </div>
    );
}
