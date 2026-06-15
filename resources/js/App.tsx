import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from '@/components/app-layout';
import { FinanceRoute, ProtectedRoute, PublicOnlyRoute } from '@/components/route-guards';
import { Spinner } from '@/components/ui/spinner';

const LoginPage = lazy(() => import('@/features/auth/login-page').then((m) => ({ default: m.LoginPage })));
const RegisterPage = lazy(() => import('@/features/auth/register-page').then((m) => ({ default: m.RegisterPage })));
const DashboardPage = lazy(() => import('@/features/dashboard/dashboard-page').then((m) => ({ default: m.DashboardPage })));
const RequestsPage = lazy(() => import('@/features/payment-requests/requests-page').then((m) => ({ default: m.RequestsPage })));
const PaymentRequestDetailPage = lazy(() =>
    import('@/features/payment-requests/detail-page').then((m) => ({ default: m.PaymentRequestDetailPage })),
);
const ApprovalsPage = lazy(() => import('@/features/finance/approvals-page').then((m) => ({ default: m.ApprovalsPage })));

function PageFallback() {
    return (
        <div className="flex h-[60vh] items-center justify-center">
            <Spinner className="size-7 text-primary" />
        </div>
    );
}

export function App() {
    return (
        <Suspense fallback={<PageFallback />}>
            <Routes>
                <Route element={<PublicOnlyRoute />}>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                </Route>

                <Route element={<ProtectedRoute />}>
                    <Route element={<AppLayout />}>
                        <Route path="/dashboard" element={<DashboardPage />} />
                        <Route path="/requests" element={<RequestsPage />} />
                        <Route path="/requests/:id" element={<PaymentRequestDetailPage />} />
                        <Route element={<FinanceRoute />}>
                            <Route path="/approvals" element={<ApprovalsPage />} />
                        </Route>
                    </Route>
                </Route>

                <Route path="/" element={<Navigate to="/dashboard" replace />} />
                <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
        </Suspense>
    );
}
