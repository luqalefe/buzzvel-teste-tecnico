import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api, getToken, setToken, UNAUTHORIZED_EVENT } from '@/lib/api';
import type { AuthResponse, User } from '@/types';

export interface Credentials {
    email: string;
    password: string;
}

export interface RegisterData {
    name: string;
    email: string;
    password: string;
    country: string;
    currency: string;
}

interface AuthContextValue {
    user: User | null;
    isLoading: boolean;
    isAuthenticated: boolean;
    login: (credentials: Credentials) => Promise<User>;
    register: (data: RegisterData) => Promise<User>;
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(() => Boolean(getToken()));

    // Restore the session from a stored token on first load.
    useEffect(() => {
        let active = true;
        if (!getToken()) {
            setIsLoading(false);
            return;
        }
        api.get<{ data: User }>('/user')
            .then((res) => {
                if (active) setUser(res.data.data);
            })
            .catch(() => {
                setToken(null);
                if (active) setUser(null);
            })
            .finally(() => {
                if (active) setIsLoading(false);
            });
        return () => {
            active = false;
        };
    }, []);

    // A 401 anywhere (expired/revoked token) clears auth → route guards send to /login.
    useEffect(() => {
        const onUnauthorized = () => setUser(null);
        window.addEventListener(UNAUTHORIZED_EVENT, onUnauthorized);
        return () => window.removeEventListener(UNAUTHORIZED_EVENT, onUnauthorized);
    }, []);

    const handleAuth = (res: AuthResponse): User => {
        setToken(res.token);
        setUser(res.user);
        return res.user;
    };

    const login = useCallback(async (credentials: Credentials) => {
        const { data } = await api.post<AuthResponse>('/login', credentials);
        return handleAuth(data);
    }, []);

    const register = useCallback(async (payload: RegisterData) => {
        const { data } = await api.post<AuthResponse>('/register', payload);
        return handleAuth(data);
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('/logout');
        } catch {
            // Even if the request fails (e.g. already-expired token), drop it locally.
        }
        setToken(null);
        setUser(null);
    }, []);

    const value = useMemo(
        () => ({ user, isLoading, isAuthenticated: Boolean(user), login, register, logout }),
        [user, isLoading, login, register, logout],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within an AuthProvider');
    return ctx;
}
