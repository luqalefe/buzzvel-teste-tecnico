import { Eye } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { Countdown } from '@/components/countdown';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate, formatEur, formatNumber } from '@/lib/format';
import type { PaymentRequest } from '@/types';
import { DecisionButtons } from './decision-buttons';

interface Props {
    requests: PaymentRequest[];
    isLoading?: boolean;
    showRequester?: boolean;
    showDecisions?: boolean;
}

export function RequestsTable({ requests, isLoading, showRequester, showDecisions }: Props) {
    const { t, i18n } = useTranslation();
    const navigate = useNavigate();
    const columns = 5 + (showRequester ? 1 : 0);

    return (
        <div className="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                        {showRequester && <TableHead>{t('requests.requester')}</TableHead>}
                        <TableHead>{t('requests.amount')}</TableHead>
                        <TableHead>{t('requests.eur')}</TableHead>
                        <TableHead>{t('requests.status')}</TableHead>
                        <TableHead className="hidden md:table-cell">{t('requests.created')}</TableHead>
                        <TableHead className="text-right">{t('common.actions')}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {isLoading
                        ? Array.from({ length: 5 }).map((_, row) => (
                              <TableRow key={row}>
                                  {Array.from({ length: columns }).map((__, col) => (
                                      <TableCell key={col}>
                                          <Skeleton className="h-5 w-24" />
                                      </TableCell>
                                  ))}
                              </TableRow>
                          ))
                        : requests.map((request) => (
                              <TableRow
                                  key={request.id}
                                  className="cursor-pointer"
                                  onClick={() => navigate(`/requests/${request.id}`)}
                              >
                                  {showRequester && (
                                      <TableCell className="font-medium">{request.user?.name ?? '—'}</TableCell>
                                  )}
                                  <TableCell>
                                      <span className="tnum">{formatNumber(request.amount, i18n.language, 2)}</span>{' '}
                                      <span className="text-muted-foreground">{request.currency}</span>
                                  </TableCell>
                                  <TableCell className="tnum font-medium">
                                      {formatEur(request.converted_amount_eur, i18n.language)}
                                  </TableCell>
                                  <TableCell>
                                      <div className="flex flex-col gap-1">
                                          <StatusBadge status={request.status} />
                                          {request.status === 'pending' && (
                                              <Countdown expiresAt={request.expires_at} className="text-xs" />
                                          )}
                                      </div>
                                  </TableCell>
                                  <TableCell className="hidden text-muted-foreground md:table-cell">
                                      {formatDate(request.created_at, i18n.language)}
                                  </TableCell>
                                  <TableCell className="text-right" onClick={(event) => event.stopPropagation()}>
                                      {showDecisions && request.status === 'pending' ? (
                                          <DecisionButtons request={request} />
                                      ) : (
                                          <Button
                                              variant="ghost"
                                              size="icon"
                                              onClick={() => navigate(`/requests/${request.id}`)}
                                              aria-label={t('requests.viewDetails')}
                                          >
                                              <Eye className="size-4" />
                                          </Button>
                                      )}
                                  </TableCell>
                              </TableRow>
                          ))}
                </TableBody>
            </Table>
        </div>
    );
}
