import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { COUNTRY_BY_NAME, EUROPEAN_COUNTRIES } from '@/data/countries';
import { LANGUAGE_CODES } from '@/i18n/languages';
import { apiErrorMessage } from '@/lib/api';
import { useAuth } from '@/providers/auth-provider';
import { CURRENCIES } from '@/types';
import { AuthShell } from './auth-shell';

type CurrencyCode = (typeof CURRENCIES)[number];

export function RegisterPage() {
    const { t, i18n } = useTranslation();
    const { register: registerUser } = useAuth();
    const navigate = useNavigate();

    const schema = z.object({
        name: z.string().min(1, t('validation.required')),
        email: z.string().min(1, t('validation.required')).email(t('validation.email')),
        password: z.string().min(8, t('validation.min', { count: 8 })),
        country: z.string().min(1, t('validation.required')),
        currency: z.enum(CURRENCIES, { message: t('validation.required') }),
    });
    type Values = z.infer<typeof schema>;

    const form = useForm<Values>({
        resolver: zodResolver(schema),
        defaultValues: { name: '', email: '', password: '', country: '', currency: undefined },
    });

    const onSubmit = async (values: Values) => {
        try {
            const user = await registerUser(values);
            toast.success(t('auth.registerSuccess', { name: user.name }));
            navigate('/dashboard', { replace: true });
        } catch (error) {
            toast.error(apiErrorMessage(error, t('create.error')));
        }
    };

    const errors = form.formState.errors;

    return (
        <AuthShell
            title={t('auth.registerTitle')}
            subtitle={t('auth.registerSubtitle')}
            footer={
                <>
                    {t('auth.haveAccount')}{' '}
                    <Link to="/login" className="font-medium text-primary hover:underline">
                        {t('auth.signIn')}
                    </Link>
                </>
            }
        >
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
                <div className="space-y-2">
                    <Label htmlFor="name">{t('auth.name')}</Label>
                    <Input id="name" autoComplete="name" {...form.register('name')} />
                    {errors.name && <p className="text-sm text-destructive">{errors.name.message}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email">{t('auth.email')}</Label>
                    <Input id="email" type="email" autoComplete="email" {...form.register('email')} />
                    {errors.email && <p className="text-sm text-destructive">{errors.email.message}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">{t('auth.password')}</Label>
                    <Input id="password" type="password" autoComplete="new-password" {...form.register('password')} />
                    {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
                </div>

                <div className="space-y-2">
                    <Label>{t('auth.country')}</Label>
                    <Controller
                        control={form.control}
                        name="country"
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={(name) => {
                                    field.onChange(name);
                                    // Selecting a country auto-fills its currency and, when we
                                    // ship that language, switches the UI to it.
                                    const country = COUNTRY_BY_NAME.get(name);
                                    if (country) {
                                        form.setValue('currency', country.currency as CurrencyCode, {
                                            shouldValidate: true,
                                        });
                                        if (LANGUAGE_CODES.includes(country.lang)) {
                                            void i18n.changeLanguage(country.lang);
                                        }
                                    }
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent>
                                    {EUROPEAN_COUNTRIES.map((country) => (
                                        <SelectItem key={country.code} value={country.name}>
                                            {country.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    />
                    {errors.country && <p className="text-sm text-destructive">{errors.country.message}</p>}
                </div>

                <div className="space-y-2">
                    <Label>{t('auth.currency')}</Label>
                    <Controller
                        control={form.control}
                        name="currency"
                        render={({ field }) => (
                            <Select value={field.value} onValueChange={field.onChange}>
                                <SelectTrigger>
                                    <SelectValue placeholder="—" />
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
                    {errors.currency && <p className="text-sm text-destructive">{errors.currency.message}</p>}
                </div>

                <Button type="submit" className="w-full" disabled={form.formState.isSubmitting}>
                    {form.formState.isSubmitting && <Spinner className="size-4" />}
                    {form.formState.isSubmitting ? t('auth.creatingAccount') : t('auth.signUp')}
                </Button>
            </form>
        </AuthShell>
    );
}
