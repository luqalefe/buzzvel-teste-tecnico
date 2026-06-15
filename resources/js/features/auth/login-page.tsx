import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { apiErrorMessage } from '@/lib/api';
import { useAuth } from '@/providers/auth-provider';
import { AuthShell } from './auth-shell';

interface LocationState {
    from?: { pathname?: string };
}

export function LoginPage() {
    const { t } = useTranslation();
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    const schema = z.object({
        email: z.string().min(1, t('validation.required')).email(t('validation.email')),
        password: z.string().min(1, t('validation.required')),
    });
    type Values = z.infer<typeof schema>;

    const form = useForm<Values>({
        resolver: zodResolver(schema),
        defaultValues: { email: '', password: '' },
    });

    const onSubmit = async (values: Values) => {
        try {
            const user = await login(values);
            toast.success(t('auth.loginSuccess', { name: user.name }));
            const to = (location.state as LocationState | null)?.from?.pathname ?? '/dashboard';
            navigate(to, { replace: true });
        } catch (error) {
            toast.error(apiErrorMessage(error, t('auth.invalidCredentials')));
        }
    };

    return (
        <AuthShell
            title={t('auth.loginTitle')}
            subtitle={t('auth.loginSubtitle')}
            footer={
                <>
                    {t('auth.noAccount')}{' '}
                    <Link to="/register" className="font-medium text-primary hover:underline">
                        {t('auth.signUp')}
                    </Link>
                </>
            }
        >
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
                <div className="space-y-2">
                    <Label htmlFor="email">{t('auth.email')}</Label>
                    <Input id="email" type="email" autoComplete="email" {...form.register('email')} />
                    {form.formState.errors.email && (
                        <p className="text-sm text-destructive">{form.formState.errors.email.message}</p>
                    )}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="password">{t('auth.password')}</Label>
                    <Input id="password" type="password" autoComplete="current-password" {...form.register('password')} />
                    {form.formState.errors.password && (
                        <p className="text-sm text-destructive">{form.formState.errors.password.message}</p>
                    )}
                </div>

                <Button type="submit" className="w-full" disabled={form.formState.isSubmitting}>
                    {form.formState.isSubmitting && <Spinner className="size-4" />}
                    {form.formState.isSubmitting ? t('auth.signingIn') : t('auth.signIn')}
                </Button>

                <p className="rounded-md bg-muted px-3 py-2 text-center text-xs text-muted-foreground">
                    {t('auth.demoHint')}
                </p>
            </form>
        </AuthShell>
    );
}
