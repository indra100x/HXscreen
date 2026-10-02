import {
    Head,
    router,
    setLayoutProps,
    useHttp,
    usePoll,
} from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import PlaylistsSection from '@/components/business/playlists-section';
import ScreensSection from '@/components/business/screens-section';
import VideosSection from '@/components/business/videos-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { destroy, update } from '@/routes/businesses';
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
            onError: () => toast.error('Could not rename business'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
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

function DeleteBusinessButton({ business }: { business: Business }) {
    const { delete: remove, processing } = useHttp();

    function onDelete() {
        if (
            !window.confirm(
                `Delete "${business.name}" and all its screens, playlists and videos?`,
            )
        ) {
            return;
        }
        void remove(destroy.url({ business: business.id }), {
            onSuccess: () => {
                toast.success('Business deleted');
                router.visit(dashboard.url());
            },
            onError: () => toast.error('Could not delete business'),
        });
    }

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={onDelete}
            disabled={processing}
            className="text-destructive hover:text-destructive"
        >
            Delete
        </Button>
    );
}

function StatCard({ title, value }: { title: string; value: number }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-3xl font-bold">{value}</div>
            </CardContent>
        </Card>
    );
}

export default function BusinessShow({
    business,
    screens,
    playlists,
    videos,
}: {
    business: Business;
    screens: Screen[];
    playlists: Playlist[];
    videos: Video[];
}) {
    const online = screens.filter(
        (s) =>
            s.paired_at &&
            s.last_seen_at &&
            Date.now() - new Date(s.last_seen_at).getTime() < 5 * 60 * 1000,
    ).length;

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

    return (
        <>
            <Head title={business.name} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={business.name}
                        description={`${online} of ${screens.length} screens online`}
                    />
                    <div className="flex gap-2">
                        <RenameBusinessDialog business={business} />
                        <DeleteBusinessButton business={business} />
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard title="Screens" value={screens.length} />
                    <StatCard title="Playlists" value={playlists.length} />
                    <StatCard title="Videos" value={videos.length} />
                </div>

                <ScreensSection
                    businessId={business.id}
                    screens={screens}
                    playlists={playlists}
                />
                <PlaylistsSection
                    businessId={business.id}
                    playlists={playlists}
                    videos={videos}
                />
                <VideosSection businessId={business.id} videos={videos} />
            </div>
        </>
    );
}
