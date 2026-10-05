import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';

export default function AppearanceToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    // SSR has no access to the stored theme; rendering the icon only on the
    // client avoids a hydration mismatch.
    if (!mounted) {
        return <span className="h-9 w-9" />;
    }

    const isDark = resolvedAppearance === 'dark';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-9 w-9"
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                >
                    <span className="sr-only">Toggle theme</span>
                    {isDark ? (
                        <Sun className="size-4" />
                    ) : (
                        <Moon className="size-4" />
                    )}
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>Switch to {isDark ? 'light' : 'dark'} mode</p>
            </TooltipContent>
        </Tooltip>
    );
}
