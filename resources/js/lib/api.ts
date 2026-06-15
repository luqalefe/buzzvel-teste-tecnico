import axios from 'axios';

export const TOKEN_KEY = 'auth_token';

export function getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null): void {
    if (token) {
        localStorage.setItem(TOKEN_KEY, token);
    } else {
        localStorage.removeItem(TOKEN_KEY);
    }
}

export const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

// Attach the bearer token to every request.
api.interceptors.request.use((config) => {
    const token = getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

/** Event the AuthProvider listens to so a mid-session 401 logs the user out. */
export const UNAUTHORIZED_EVENT = 'auth:unauthorized';

// A 401 on any protected request means the token is gone/expired/revoked:
// drop it and signal the app to reset auth state (→ redirect to /login).
// Login/register 401s are left alone (those are "invalid credentials").
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const url: string = error?.config?.url ?? '';
        const isAuthEndpoint = url.includes('/login') || url.includes('/register');

        if (status === 401 && !isAuthEndpoint) {
            setToken(null);
            window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
        }

        return Promise.reject(error);
    },
);

/**
 * Pull a human-friendly message out of a Laravel error response, falling back
 * to the first validation error or a generic fallback.
 */
export function apiErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
        if (data?.errors) {
            const first = Object.values(data.errors)[0];
            if (first?.length) return first[0];
        }
        if (data?.message) return data.message;
    }
    return fallback;
}
