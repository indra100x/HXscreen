import {
    Head,
    Link,
    router,
    setLayoutProps,
    useHttp,
    usePoll,
} from '@inertiajs/react';
import {
    ChartColumn,
    Clapperboard,
    ListVideo,
    MonitorPlay,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import PlaylistsSection from '@/components/business/playlists-section';
import ScreensSection from '@/components/business/screens-section';
import TeamSection, { type Member } from '@/components/business/team-section';
import VideosSection from '@/components/business/videos-section';
import ActivitySection, {
    type ActivityEntry,
} from '@/components/business/activity-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { analytics as businessAnalytics } from '@/routes/business';
import { update } from '@/routes/businesses';
import { toastApiError } from '@/lib/api-errors';
import type { Business, Playlist, Screen, Video } from '@/types';

function RenameBusinessDialog({ business }: { business: Business }) {
    const [open, setOpen] = useState(false);
    const { data, setData, put, processing, errors } = useHttp({
        name: business.name,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void put(update.url({ business: business.id }), {
            onSuccess: () => {
                setOpen(false);
                toast.success('Business renamed');
                router.reload();
            },
            onError: toastApiError('Could not rename business'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-3.5" />
                    Rename
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rename business</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="rename-name">Name</Label>
                        <Input
                            id="rename-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            maxLength={50}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function StatCard({
    icon: Icon,
    title,
    value,
    sub,
}: {
    icon: typeof MonitorPlay;
    title: string;
    value: number;
    sub?: string;
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
                        {sub ? ` · ${sub}` : ''}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

type Tab = 'screens' | 'playlists' | 'videos' | 'team' | 'activity';

export default function BusinessShow({
    business,
    isOwner,
    members,
    screens,
    playlists,
    videos,
    unpairedScreens,
    activity,
}: {
    business: Business;
    isOwner: boolean;
    members: Member[];
    screens: Screen[];
    playlists: Playlist[];
    videos: Video[];
    unpairedScreens: Screen[];
    activity: ActivityEntry[];
}) {
    const online = screens.filter(
        (s) =>
            s.paired_at &&
            s.last_seen_at &&
            Date.now() - new Date(s.last_seen_at).getTime() < 5 * 60 * 1000,
    ).length;

    const [tab, setTab] = useState<Tab>('screens');

    usePoll(30000);

    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard.url(),
            },
            {
                title: business.name,
            },
        ],
    });

    const tabs: { id: Tab; label: string; count: number | null }[] = [
        { id: 'screens', label: 'Screens', count: screens.length },
        { id: 'playlists', label: 'Playlists', count: playlists.length },
        { id: 'videos', label: 'Videos', count: videos.length },
        { id: 'team', label: 'Team', count: members.length || null },
        { id: 'activity', label: 'Activity', count: null },
    ];

    return (
        <>
            <Head title={business.name} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                            <MonitorPlay className="size-6" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold tracking-tight">
                                {business.name}
                            </h1>
                            <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                <span className="relative flex size-2">
                                    <span
                                        className={`absolute inline-flex size-full rounded-full opacity-60 ${
                                            online > 0
                                                ? 'animate-ping bg-emerald-600'
                                                : 'bg-muted-foreground'
                                        }`}
                                    />
                                    <span
                                        className={`relative inline-flex size-2 rounded-full ${
                                            online > 0
                                                ? 'bg-emerald-600'
                                                : 'bg-muted-foreground'
                                        }`}
                                    />
                                </span>
                                {online} of {screens.length} screens online
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={businessAnalytics.url({
                                    business: business.id,
                                })}
                            >
                                <ChartColumn className="size-3.5" />
                                Analytics
                            </Link>
                        </Button>
                        {isOwner && (
                            <RenameBusinessDialog business={business} />
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        icon={MonitorPlay}
                        title="Screens"
                        value={screens.length}
                        sub={`${online} online`}
                    />
                    <StatCard
                        icon={ListVideo}
                        title="Playlists"
                        value={playlists.length}
                    />
                    <StatCard
                        icon={Clapperboard}
                        title="Videos"
                        value={videos.length}
                    />
                </div>

                <div className="sticky top-0 z-10 -mx-4 border-b bg-background/95 px-4 py-2 backdrop-blur">
                    <div className="flex gap-1">
                        {tabs.map((t) => (
                            <Button
                                key={t.id}
                                variant={tab === t.id ? 'secondary' : 'ghost'}
                                size="sm"
                                onClick={() => setTab(t.id)}
                                className="gap-2"
                            >
                                {t.label}
                                {t.count !== null && (
                                    <span className="rounded-full bg-muted px-1.5 text-xs font-semibold">
                                        {t.count}
                                    </span>
                                )}
                            </Button>
                        ))}
                    </div>
                </div>

                {tab === 'screens' && (
                    <ScreensSection
                        businessId={business.id}
                        screens={screens}
                        playlists={playlists}
                        unpairedScreens={unpairedScreens}
                    />
                )}
                {tab === 'playlists' && (
                    <PlaylistsSection
                        businessId={business.id}
                        playlists={playlists}
                        videos={videos}
                    />
                )}
                {tab === 'videos' && (
                    <VideosSection businessId={business.id} videos={videos} />
                )}
                {tab === 'team' && (
                    <TeamSection
                        businessId={business.id}
                        isOwner={isOwner}
                        members={members}
                    />
                )}
                {tab === 'activity' && <ActivitySection entries={activity} />}
            </div>
        </>
    );
}
