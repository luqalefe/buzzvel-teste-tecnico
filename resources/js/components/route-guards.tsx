import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { Spinner } from '@/components/ui/spinner';
import { useAuth } from '@/providers/auth-provider';

function FullScreenSpinner() {
    return (
        <div className="flex h-dvh items-center justify-center">
            <Spinner className="size-8 text-primary" />
        </div>
    );
}

/** Requires an authenticated user; otherwise redirects to /login. */
export function ProtectedRoute() {
    const { isAuthenticated, isLoading } = useAuth();
    const location = useLocation();

    if (isLoading) return <FullScreenSpinner />;
    if (!isAuthenticated) return <Navigate to="/login" replace state={{ from: location }} />;
    return <Outlet />;
}

/** For guest-only pages (login/register); redirects authenticated users away. */
export function PublicOnlyRoute() {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) return <FullScreenSpinner />;
    if (isAuthenticated) return <Navigate to="/dashboard" replace />;
    return <Outlet />;
}

/** Finance-only pages (the approvals queue). */
export function FinanceRoute() {
    const { user, isLoading } = useAuth();

    if (isLoading) return <FullScreenSpinner />;
    if (user?.role !== 'finance') return <Navigate to="/dashboard" replace />;
    return <Outlet />;
}
