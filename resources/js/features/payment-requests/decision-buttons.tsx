import { Check, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useDecidePaymentRequest } from '@/hooks/use-payment-requests';
import { apiErrorMessage } from '@/lib/api';
import type { PaymentRequest } from '@/types';

export function DecisionButtons({
    request,
    size = 'sm',
}: {
    request: PaymentRequest;
    size?: 'sm' | 'default';
}) {
    const { t } = useTranslation();
    const decide = useDecidePaymentRequest();

    if (request.status !== 'pending') return null;

    const run = (status: 'approved' | 'rejected') => {
        decide.mutate(
            { id: request.id, status },
            {
                onSuccess: () => toast.success(status === 'approved' ? t('decide.approved') : t('decide.rejected')),
                onError: (error) => toast.error(apiErrorMessage(error, t('decide.error'))),
            },
        );
    };

    const busy = decide.isPending;
    const busyStatus = decide.variables?.status;

    return (
        <div className="flex items-center gap-2">
            <Button size={size} variant="success" disabled={busy} onClick={() => run('approved')}>
                {busy && busyStatus === 'approved' ? <Spinner className="size-4" /> : <Check className="size-4" />}
                {t('decide.approve')}
            </Button>
            <Button size={size} variant="destructive" disabled={busy} onClick={() => run('rejected')}>
                {busy && busyStatus === 'rejected' ? <Spinner className="size-4" /> : <X className="size-4" />}
                {t('decide.reject')}
            </Button>
        </div>
    );
}
