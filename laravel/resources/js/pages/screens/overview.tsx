import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { Building2, MonitorPlay } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';
import { show as businessShow } from '@/routes/business';
import type { Screen } from '@/types';

function statusOf(screen: Screen): {
    label: string;
    variant: 'default' | 'secondary' | 'outline';
    live: boolean;
} {
    if (!screen.paired_at) {
        return { label: 'Unpaired', variant: 'outline', live: false };
    }
    if (
        screen.last_seen_at &&
        Date.now() - new Date(screen.last_seen_at).getTime() < 5 * 60 * 1000
    ) {
        return { label: 'Online', variant: 'default', live: true };
    }
    return { label: 'Offline', variant: 'secondary', live: false };
}

export default function ScreensOverview({ screens }: { screens: Screen[] }) {
    usePoll(30000);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard.url() },
            { title: 'Screens' },
        ],
    });

    const online = screens.filter((s) => statusOf(s).live).length;

    return (
        <>
            <Head title="Screens" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="All screens"
                    description={`${online} of ${screens.length} online across all your businesses.`}
                />

                {screens.length === 0 ? (
                    <Card>
                        <CardContent className="py-8 text-center text-sm text-muted-foreground">
                            No screens yet. Pair your first TV from the
                            dashboard.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {screens.map((screen) => {
                            const status = statusOf(screen);

                            return (
                                <Card key={screen.id}>
                                    <CardContent className="flex items-center gap-3 pt-6">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <MonitorPlay className="size-5" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate font-semibold">
                                                    {screen.name}
                                                </span>
                                                <Badge variant={status.variant}>
                                                    {status.label}
                                                </Badge>
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-1 text-sm text-muted-foreground">
                                                <Building2 className="size-3.5" />
                                                {screen.business ? (
                                                    <Link
                                                        href={businessShow.url({
                                                            business:
                                                                screen.business
                                                                    .id,
                                                        })}
                                                        className="truncate hover:underline"
                                                    >
                                                        {screen.business.name}
                                                    </Link>
                                                ) : (
                                                    'Unpaired'
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}
