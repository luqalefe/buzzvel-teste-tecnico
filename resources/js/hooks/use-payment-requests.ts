import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Paginated, PaymentRequest, PaymentStatus, Stats } from '@/types';

export interface ListParams {
    status?: PaymentStatus | 'all';
    page?: number;
    perPage?: number;
}

export function usePaymentRequests(params: ListParams) {
    return useQuery({
        queryKey: ['payment-requests', 'list', params],
        queryFn: async () => {
            const { data } = await api.get<Paginated<PaymentRequest>>('/payment-requests', {
                params: {
                    status: params.status && params.status !== 'all' ? params.status : undefined,
                    page: params.page,
                    per_page: params.perPage,
                },
            });
            return data;
        },
        placeholderData: keepPreviousData,
    });
}

export function usePaymentRequest(id: number | string | undefined) {
    return useQuery({
        queryKey: ['payment-requests', 'detail', id],
        enabled: id !== undefined && id !== null,
        queryFn: async () => {
            const { data } = await api.get<{ data: PaymentRequest }>(`/payment-requests/${id}`);
            return data.data;
        },
    });
}

export function useStats() {
    return useQuery({
        queryKey: ['payment-requests', 'stats'],
        queryFn: async () => {
            const { data } = await api.get<{ data: Stats }>('/payment-requests/stats');
            return data.data;
        },
    });
}

export interface ConversionPreview {
    currency: string;
    exchange_rate: number;
    rate_source: string;
    converted_amount_eur: number;
}

export function useConversionPreview(amount: number | undefined, currency: string | undefined) {
    return useQuery({
        queryKey: ['payment-requests', 'preview', currency, amount],
        enabled: Boolean(currency) && typeof amount === 'number' && amount > 0,
        retry: false,
        staleTime: 60_000,
        queryFn: async () => {
            const { data } = await api.get<{ data: ConversionPreview }>('/payment-requests/preview', {
                params: { amount, currency },
            });
            return data.data;
        },
    });
}

export interface CreatePayload {
    amount: number;
    currency: string;
    description?: string;
}

export function useCreatePaymentRequest() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async (payload: CreatePayload) => {
            const { data } = await api.post<{ data: PaymentRequest }>('/payment-requests', payload);
            return data.data;
        },
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['payment-requests'] });
        },
    });
}

export interface DecidePayload {
    id: number;
    status: 'approved' | 'rejected';
}

export function useDecidePaymentRequest() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, status }: DecidePayload) => {
            const { data } = await api.patch<{ data: PaymentRequest }>(`/payment-requests/${id}`, { status });
            return data.data;
        },
        onSuccess: (updated) => {
            void queryClient.invalidateQueries({ queryKey: ['payment-requests'] });
            queryClient.setQueryData(['payment-requests', 'detail', String(updated.id)], updated);
        },
    });
}
