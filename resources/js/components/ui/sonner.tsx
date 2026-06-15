import * as React from 'react';
import { Toaster as Sonner } from 'sonner';
import { useTheme } from '@/providers/theme-provider';

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
    const { resolved } = useTheme();

    return (
        <Sonner
            theme={resolved}
            className="toaster group"
            {...props}
        />
    );
};
Toaster.displayName = 'Toaster';

export { Toaster };
