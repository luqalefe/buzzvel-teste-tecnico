import { CheckCircle2, Clock, TimerOff, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { PaymentStatus } from '@/types';

const MAP = {
    pending: { variant: 'warning', Icon: Clock },
    approved: { variant: 'success', Icon: CheckCircle2 },
    rejected: { variant: 'destructive', Icon: XCircle },
    expired: { variant: 'outline', Icon: TimerOff },
} as const;

export function StatusBadge({ status, className }: { status: PaymentStatus; className?: string }) {
    const { t } = useTranslation();
    const { variant, Icon } = MAP[status];

    return (
        <Badge variant={variant} className={className}>
            <Icon className="mr-1 size-3" />
            {t(`status.${status}`)}
        </Badge>
    );
}
