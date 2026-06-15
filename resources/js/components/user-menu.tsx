import { LogOut } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuth } from '@/providers/auth-provider';

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('');
}

export function UserMenu() {
    const { user, logout } = useAuth();
    const { t } = useTranslation();
    const navigate = useNavigate();

    if (!user) return null;

    const onLogout = async () => {
        await logout();
        toast.success(t('auth.logoutSuccess'));
        navigate('/login');
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-9 gap-2 px-2">
                    <Avatar className="h-7 w-7">
                        <AvatarFallback className="bg-primary text-xs text-primary-foreground">
                            {initials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden text-sm font-medium sm:inline">{user.name}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuLabel>
                    <div className="flex flex-col gap-0.5">
                        <span className="text-sm font-medium">{user.name}</span>
                        <span className="text-xs font-normal text-muted-foreground">{user.email}</span>
                        <span className="mt-1 text-xs font-normal text-muted-foreground">
                            {t(`roles.${user.role}`)} · {user.country} · {user.currency}
                        </span>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={onLogout}>
                    <LogOut className="size-4" /> {t('auth.logout')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
