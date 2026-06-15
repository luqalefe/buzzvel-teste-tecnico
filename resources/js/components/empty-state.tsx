import { Inbox } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

export function EmptyState({
    icon: Icon = Inbox,
    title,
    children,
}: {
    icon?: ComponentType<{ className?: string }>;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex animate-fade-in flex-col items-center justify-center gap-4 rounded-xl border border-dashed bg-card/40 p-12 text-center">
            <div className="relative flex size-16 items-center justify-center rounded-2xl bg-muted">
                <Icon className="size-7 text-muted-foreground" />
                {/* small coral mark — a touch of brand personality */}
                <span className="absolute -right-1 -top-1 size-3.5 rounded-full border-2 border-card bg-brand" />
            </div>
            <p className="font-display text-lg font-semibold">{title}</p>
            {children && (
                <div className="-mt-1 flex flex-col items-center gap-3 text-sm text-muted-foreground">{children}</div>
            )}
        </div>
    );
}
