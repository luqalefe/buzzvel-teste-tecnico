import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { I18nextProvider } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';
import { App } from '@/App';
import { Toaster } from '@/components/ui/sonner';
import i18n from '@/i18n';
import { AuthProvider } from '@/providers/auth-provider';
import { ThemeProvider } from '@/providers/theme-provider';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: { retry: 1, refetchOnWindowFocus: false, staleTime: 30_000 },
    },
});

const rootElement = document.getElementById('root');
if (!rootElement) {
    throw new Error('Root element #root not found');
}

createRoot(rootElement).render(
    <StrictMode>
        <I18nextProvider i18n={i18n}>
            <ThemeProvider>
                <QueryClientProvider client={queryClient}>
                    <BrowserRouter>
                        <AuthProvider>
                            <App />
                            <Toaster richColors closeButton position="top-right" />
                        </AuthProvider>
                    </BrowserRouter>
                </QueryClientProvider>
            </ThemeProvider>
        </I18nextProvider>
    </StrictMode>,
);
