import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { PageMeta } from '@/types';

export function Pagination({ meta, page, onPage }: { meta?: PageMeta; page: number; onPage: (page: number) => void }) {
    const { t } = useTranslation();
    if (!meta || meta.last_page <= 1) return null;

    return (
        <div className="mt-4 flex items-center justify-between gap-2">
            <p className="text-sm text-muted-foreground">
                {t('pagination.showing', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total })}
            </p>
            <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
                    <ChevronLeft className="size-4" />
                    {t('pagination.previous')}
                </Button>
                <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => onPage(page + 1)}>
                    {t('pagination.next')}
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}
