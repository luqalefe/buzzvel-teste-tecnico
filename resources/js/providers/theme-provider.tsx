import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

export type Theme = 'light' | 'dark' | 'system';
type Resolved = 'light' | 'dark';

interface ThemeContextValue {
    theme: Theme;
    resolved: Resolved;
    setTheme: (theme: Theme) => void;
}

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

function systemTheme(): Resolved {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme: Theme): Resolved {
    const resolved = theme === 'system' ? systemTheme() : theme;
    document.documentElement.classList.toggle('dark', resolved === 'dark');
    return resolved;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setThemeState] = useState<Theme>(() => (localStorage.getItem('theme') as Theme) || 'system');
    const [resolved, setResolved] = useState<Resolved>(() => (theme === 'system' ? systemTheme() : theme));

    useEffect(() => {
        setResolved(applyTheme(theme));
    }, [theme]);

    useEffect(() => {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            if (theme === 'system') setResolved(applyTheme('system'));
        };
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [theme]);

    const setTheme = useCallback((next: Theme) => {
        localStorage.setItem('theme', next);
        setThemeState(next);
    }, []);

    const value = useMemo(() => ({ theme, resolved, setTheme }), [theme, resolved, setTheme]);

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

// eslint-disable-next-line react-refresh/only-export-components
export function useTheme(): ThemeContextValue {
    const ctx = useContext(ThemeContext);
    if (!ctx) throw new Error('useTheme must be used within a ThemeProvider');
    return ctx;
}
