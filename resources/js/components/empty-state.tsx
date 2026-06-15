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
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-6" />
            </div>
            <p className="text-sm font-medium">{title}</p>
            {children}
        </div>
    );
}
