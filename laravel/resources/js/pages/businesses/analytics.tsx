import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Clock, MonitorPlay, Play } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as businessShow } from '@/routes/business';
import type { Business } from '@/types';

type DayStat = {
    date: string;
    seconds: number;
};

type NamedStat = {
    name: string;
    seconds: number;
};

function formatDuration(totalSeconds: number): string {
    if (totalSeconds < 60) {
        return `${totalSeconds}s`;
    }
    if (totalSeconds < 3600) {
        return `${Math.floor(totalSeconds / 60)}m`;
    }
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
}

function StatCard({
    icon: Icon,
    title,
    value,
}: {
    icon: typeof Clock;
    title: string;
    value: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 pt-6">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Icon className="size-5" />
                </span>
                <div>
                    <div className="text-2xl leading-none font-bold">
                        {value}
                    </div>
                    <div className="mt-1 text-sm text-muted-foreground">
                        {title}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function DailyChart({ daily }: { daily: DayStat[] }) {
    const max = Math.max(1, ...daily.map((d) => Number(d.seconds)));

    if (daily.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                No playback yet — stats appear here once screens start playing.
            </p>
        );
    }

    return (
        <div className="flex h-40 items-end gap-1.5">
            {daily.map((day) => (
                <div
                    key={day.date}
                    title={`${day.date}: ${formatDuration(Number(day.seconds))}`}
                    className="group relative flex-1 self-stretch"
                >
                    <div
                        className="absolute inset-x-0 bottom-0 rounded-sm bg-primary/80 transition-colors group-hover:bg-primary"
                        style={{
                            height: `${Math.max(3, (Number(day.seconds) / max) * 100)}%`,
                        }}
                    />
                </div>
            ))}
        </div>
    );
}

function BreakdownTable({ title, rows }: { title: string; rows: NamedStat[] }) {
    const total = rows.reduce((sum, row) => sum + Number(row.seconds), 0);

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        Nothing played yet.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {rows.map((row) => (
                            <li
                                key={row.name}
                                className="flex items-baseline justify-between gap-4 text-sm"
                            >
                                <span className="min-w-0 flex-1 truncate font-medium">
                                    {row.name}
                                </span>
                                <span className="shrink-0 text-muted-foreground">
                                    {formatDuration(Number(row.seconds))}
                                    {total > 0 && (
                                        <>
                                            {' '}
                                            ·{' '}
                                            {Math.round(
                                                (Number(row.seconds) / total) *
                                                    100,
                                            )}
                                            %
                                        </>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

export default function BusinessAnalytics({
    business,
    totalSeconds,
    todaySeconds,
    daily,
    perScreen,
    perVideo,
}: {
    business: Business;
    totalSeconds: number;
    todaySeconds: number;
    daily: DayStat[];
    perScreen: NamedStat[];
    perVideo: NamedStat[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard.url() },
            {
                title: business.name,
                href: businessShow.url({ business: business.id }),
            },
            { title: 'Analytics' },
        ],
    });

    return (
        <>
            <Head title={`${business.name} analytics`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                            <Clock className="size-6" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold tracking-tight">
                                {business.name} analytics
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Play time across all screens, last 30 days
                            </p>
                        </div>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={businessShow.url({ business: business.id })}
                        >
                            <ArrowLeft className="size-4" />
                            Back to business
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        icon={Clock}
                        title="Played today"
                        value={formatDuration(todaySeconds)}
                    />
                    <StatCard
                        icon={Play}
                        title="Played last 30 days"
                        value={formatDuration(totalSeconds)}
                    />
                    <StatCard
                        icon={MonitorPlay}
                        title="Screens with play time"
                        value={String(perScreen.length)}
                    />
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">
                            Daily play time, last 14 days
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DailyChart daily={daily} />
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    <BreakdownTable title="By screen" rows={perScreen} />
                    <BreakdownTable title="By video" rows={perVideo} />
                </div>
            </div>
        </>
    );
}
