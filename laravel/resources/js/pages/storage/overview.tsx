import { Head, setLayoutProps } from '@inertiajs/react';
import { Database, Film, HardDrive } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';

type BusinessStorage = {
    id: string;
    name: string;
    videos_count: number;
    bytes: number;
};

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes / 1024;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }
    return `${value.toFixed(value < 10 ? 1 : 0)} ${units[unit]}`;
}

export default function StorageOverview({
    businesses,
    totalBytes,
    totalVideos,
}: {
    businesses: BusinessStorage[];
    totalBytes: number;
    totalVideos: number;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard.url() },
            { title: 'Storage' },
        ],
    });

    const max = Math.max(1, ...businesses.map((b) => b.bytes));

    return (
        <>
            <Head title="Storage" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Storage"
                    description="Video files stored in Cloudflare R2, by business."
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <HardDrive className="size-5" />
                            </span>
                            <div>
                                <div className="text-2xl leading-none font-bold">
                                    {formatBytes(totalBytes)}
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    Total used
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Film className="size-5" />
                            </span>
                            <div>
                                <div className="text-2xl leading-none font-bold">
                                    {totalVideos}
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    Videos stored
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Database className="size-4" />
                            Usage by business
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {businesses.length === 0 ? (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                No businesses yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {businesses.map((business) => (
                                    <li key={business.id} className="space-y-1">
                                        <div className="flex items-baseline justify-between gap-4 text-sm">
                                            <span className="min-w-0 flex-1 truncate font-medium">
                                                {business.name}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground">
                                                {business.videos_count} videos ·{' '}
                                                {formatBytes(business.bytes)}
                                            </span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary/80"
                                                style={{
                                                    width: `${Math.max(2, (business.bytes / max) * 100)}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
