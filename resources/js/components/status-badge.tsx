import { Ban, CheckCircle2, Clock, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PaymentStatus } from '@/types';
import { cn } from '@/lib/utils';

const MAP = {
    pending: { Icon: Clock, cls: 'bg-status-pending/10 text-status-pending border-status-pending/25' },
    approved: { Icon: CheckCircle2, cls: 'bg-status-approved/10 text-status-approved border-status-approved/25' },
    rejected: { Icon: XCircle, cls: 'bg-status-rejected/10 text-status-rejected border-status-rejected/25' },
    expired: { Icon: Ban, cls: 'bg-status-expired/10 text-status-expired border-status-expired/25' },
} as const;

export function StatusBadge({ status, className }: { status: PaymentStatus; className?: string }) {
    const { t } = useTranslation();
    const { Icon, cls } = MAP[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium',
                cls,
                className,
            )}
        >
            <Icon className="size-3" />
            {t(`status.${status}`)}
        </span>
    );
}
