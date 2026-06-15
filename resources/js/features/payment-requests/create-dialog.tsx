import { zodResolver } from '@hookform/resolvers/zod';
import axios from 'axios';
import { AlertCircle, ArrowRight, Plus } from 'lucide-react';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useConversionPreview, useCreatePaymentRequest } from '@/hooks/use-payment-requests';
import { useDebounce } from '@/hooks/use-debounce';
import { apiErrorMessage } from '@/lib/api';
import { formatEur, formatNumber } from '@/lib/format';
import { useAuth } from '@/providers/auth-provider';
import { CURRENCIES } from '@/types';

type CurrencyCode = (typeof CURRENCIES)[number];

export function CreateRequestDialog() {
    const { t, i18n } = useTranslation();
    const { user } = useAuth();
    const create = useCreatePaymentRequest();
    const [open, setOpen] = useState(false);

    const schema = z.object({
        amount: z
            .string()
            .min(1, t('validation.required'))
            .refine((v) => !Number.isNaN(Number(v)) && Number(v) > 0, t('validation.amountPositive')),
        currency: z.enum(CURRENCIES),
        description: z.string().max(1000).optional(),
    });
    type Values = z.infer<typeof schema>;

    const defaultCurrency = (CURRENCIES as readonly string[]).includes(user?.currency ?? '')
        ? (user!.currency as CurrencyCode)
        : 'EUR';

    const form = useForm<Values>({
        resolver: zodResolver(schema),
        defaultValues: { amount: '', currency: defaultCurrency, description: '' },
    });

    const amountStr = useDebounce(form.watch('amount'), 400);
    const currency = form.watch('currency');
    const amountNum = Number(amountStr);
    const validAmount = amountStr !== '' && !Number.isNaN(amountNum) && amountNum > 0;
    const isEur = currency === 'EUR';

    const preview = useConversionPreview(validAmount && !isEur ? amountNum : undefined, !isEur ? currency : undefined);

    const onSubmit = (values: Values) => {
        create.mutate(
            {
                amount: Number(values.amount),
                currency: values.currency,
                description: values.description?.trim() || undefined,
            },
            {
                onSuccess: () => {
                    toast.success(t('create.success'));
                    setOpen(false);
                    form.reset({ amount: '', currency: defaultCurrency, description: '' });
                },
                onError: (error) => {
                    const status = axios.isAxiosError(error) ? error.response?.status : undefined;
                    toast.error(status === 503 ? t('create.unavailable') : apiErrorMessage(error, t('create.error')));
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" />
                    {t('requests.new')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('create.title')}</DialogTitle>
                    <DialogDescription>{t('create.description')}</DialogDescription>
                </DialogHeader>

                <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-2 space-y-2">
                            <Label htmlFor="amount">{t('create.amountLabel')}</Label>
                            <Input id="amount" type="number" step="0.01" min="0" {...form.register('amount')} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('create.currencyLabel')}</Label>
                            <Controller
                                control={form.control}
                                name="currency"
                                render={({ field }) => (
                                    <Select value={field.value} onValueChange={field.onChange}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {CURRENCIES.map((code) => (
                                                <SelectItem key={code} value={code}>
                                                    {code}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        </div>
                    </div>
                    {form.formState.errors.amount && (
                        <p className="text-sm text-destructive">{form.formState.errors.amount.message}</p>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="description">
                            {t('create.descriptionLabel')}{' '}
                            <span className="text-xs font-normal text-muted-foreground">({t('common.optional')})</span>
                        </Label>
                        <Textarea
                            id="description"
                            placeholder={t('create.descriptionPlaceholder')}
                            {...form.register('description')}
                        />
                    </div>

                    {validAmount && (
                        <div className="rounded-lg border bg-muted/40 p-3 text-sm">
                            <div className="mb-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {t('create.preview')}
                            </div>
                            {isEur ? (
                                <div className="font-semibold">
                                    <span className="tnum">{formatEur(amountNum, i18n.language)}</span>{' '}
                                    <span className="font-normal text-muted-foreground">— {t('create.previewEur')}</span>
                                </div>
                            ) : preview.isLoading ? (
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <Spinner className="size-3.5" /> {t('create.previewLoading')}
                                </div>
                            ) : preview.isError ? (
                                <div className="flex items-center gap-1.5 text-status-pending">
                                    <AlertCircle className="size-3.5" /> {t('create.previewError')}
                                </div>
                            ) : preview.data ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        <span className="tnum">{formatNumber(amountNum, i18n.language, 2)}</span> {currency}
                                    </span>
                                    <ArrowRight className="size-3.5 text-muted-foreground" />
                                    <span className="tnum font-semibold text-primary">
                                        {formatEur(preview.data.converted_amount_eur, i18n.language)}
                                    </span>
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        @ <span className="tnum">{formatNumber(preview.data.exchange_rate, i18n.language, 4)}</span>
                                    </span>
                                </div>
                            ) : null}
                        </div>
                    )}

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={create.isPending}>
                            {create.isPending && <Spinner className="size-4" />}
                            {create.isPending ? t('create.submitting') : t('create.submit')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
