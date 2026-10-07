import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'setup':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Non-validation HTTP failures (401/403/419/500) never reach form onError
// handlers, so surface them globally — otherwise requests fail silently.
router.on('httpException', (event) => {
    const detail = event.detail as unknown as {
        response?: { status?: number; data?: { message?: unknown } };
        status?: number;
    };
    const status = detail.response?.status ?? detail.status;
    const serverMessage = detail.response?.data?.message;

    if (typeof serverMessage === 'string' && serverMessage) {
        toast.error(serverMessage);
    } else if (status === 401) {
        toast.error('Please log in again.');
    } else if (status === 419) {
        toast.error('Session expired — refresh the page and retry.');
    } else {
        toast.error('Request failed. Please try again.');
    }
});
