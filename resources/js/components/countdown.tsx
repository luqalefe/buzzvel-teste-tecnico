import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

/**
 * Live "expires in Xh Ym" label for a pending request's 48h TTL.
 * Re-renders every 30s; turns urgent (warning colour) under 6 hours.
 */
export function Countdown({ expiresAt, className }: { expiresAt: string | null; className?: string }) {
    const { t } = useTranslation();
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 30_000);
        return () => window.clearInterval(id);
    }, []);

    if (!expiresAt) return null;

    const diff = new Date(expiresAt).getTime() - now;

    if (diff <= 0) {
        return <span className={cn('text-warning', className)}>{t('countdown.overdue')}</span>;
    }

    const totalMinutes = Math.floor(diff / 60_000);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    const label = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
    const urgent = diff < 6 * 60 * 60 * 1000;

    return (
        <span className={cn(urgent ? 'text-warning' : 'text-muted-foreground', className)}>
            {t('countdown.expiresIn', { time: label })}
        </span>
    );
}
