import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import RenameDialog from '@/components/business/rename-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, store, update } from '@/routes/playlists';
import {
    destroy as removeVideo,
    order as orderVideos,
    store as addVideo,
} from '@/routes/playlists/videos';
import { toastApiError } from '@/lib/api-errors';
import type { Playlist, Video } from '@/types';

function CreatePlaylistDialog({ businessId }: { businessId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useHttp({
        name: '',
        busniss_id: businessId,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(store.url(), {
            onSuccess: () => {
                setData('name', '');
                setOpen(false);
                toast.success('Playlist created');
                router.reload();
            },
            onError: toastApiError('Could not create playlist'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>New playlist</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New playlist</DialogTitle>
                    <DialogDescription>
                        Playlists group videos in a play order.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="playlist-name">Name</Label>
                        <Input
                            id="playlist-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            maxLength={50}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddVideoDialog({
    playlist,
    videos,
}: {
    playlist: Playlist;
    videos: Video[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useHttp({
        video_id: '',
    });

    const attachedIds = new Set((playlist.videos ?? []).map((v) => v.id));
    const available = videos.filter((v) => !attachedIds.has(v.id));

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(addVideo.url({ playlist: playlist.id }), {
            onSuccess: () => {
                setOpen(false);
                toast.success('Video added');
                router.reload();
            },
            onError: toastApiError('Could not add video'),
        });
    }

    if (available.length === 0) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Add video
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add video</DialogTitle>
                    <DialogDescription>
                        Choose a video to append to {playlist.name}.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Select
                        value={data.video_id}
                        onValueChange={(value) => setData('video_id', value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select a video" />
                        </SelectTrigger>
                        <SelectContent>
                            {available.map((v) => (
                                <SelectItem key={v.id} value={v.id}>
                                    {v.name ?? v.url}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.video_id} />
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || !data.video_id}
                        >
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PlaylistCard({
    playlist,
    videos,
}: {
    playlist: Playlist;
    videos: Video[];
}) {
    const {
        delete: remove,
        put,
        transform,
        processing,
    } = useHttp({
        video_ids: [] as string[],
    });
    const ordered = [...(playlist.videos ?? [])];

    function onDelete() {
        if (!window.confirm(`Delete playlist "${playlist.name}"?`)) {
            return;
        }
        void remove(destroy.url({ playlist: playlist.id }), {
            onSuccess: () => {
                toast.success('Playlist deleted');
                router.reload();
            },
            onError: toastApiError('Could not delete playlist'),
        });
    }

    function onRemoveVideo(videoId: string) {
        void remove(
            removeVideo.url(
                { playlist: playlist.id },
                { query: { video_id: videoId } },
            ),
            {
                onSuccess: () => router.reload(),
                onError: toastApiError('Could not remove video'),
            },
        );
    }

    function move(videoId: string, direction: -1 | 1) {
        const ids = ordered.map((v) => v.id);
        const from = ids.indexOf(videoId);
        const to = from + direction;
        if (from < 0 || to < 0 || to >= ids.length) {
            return;
        }
        [ids[from], ids[to]] = [ids[to], ids[from]];
        transform(() => ({ video_ids: ids }));
        void put(orderVideos.url({ playlist: playlist.id }), {
            onSuccess: () => router.reload(),
            onError: toastApiError('Could not reorder videos'),
        });
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle>{playlist.name}</CardTitle>
                        <CardDescription>
                            {ordered.length} video
                            {ordered.length === 1 ? '' : 's'}
                        </CardDescription>
                    </div>
                    <div className="flex shrink-0 items-center">
                        <RenameDialog
                            title="playlist"
                            currentName={playlist.name}
                            url={update.url({ playlist: playlist.id })}
                            successMessage="Playlist renamed"
                        />
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onDelete}
                            disabled={processing}
                            className="text-destructive hover:text-destructive"
                        >
                            Delete
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {ordered.length > 0 && (
                    <ol className="space-y-1">
                        {ordered.map((video, index) => (
                            <li
                                key={video.id}
                                className="flex items-center justify-between gap-2 rounded-md border px-2 py-1 text-sm"
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {index + 1}. {video.name ?? video.url}
                                </span>
                                <div className="flex shrink-0 gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        disabled={index === 0 || processing}
                                        onClick={() => move(video.id, -1)}
                                    >
                                        ↑
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        disabled={
                                            index === ordered.length - 1 ||
                                            processing
                                        }
                                        onClick={() => move(video.id, 1)}
                                    >
                                        ↓
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onRemoveVideo(video.id)}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
                <AddVideoDialog playlist={playlist} videos={videos} />
            </CardContent>
        </Card>
    );
}

export default function PlaylistsSection({
    businessId,
    playlists,
    videos,
}: {
    businessId: string;
    playlists: Playlist[];
    videos: Video[];
}) {
    const [query, setQuery] = useState('');
    const filtered = playlists.filter((playlist) =>
        playlist.name.toLowerCase().includes(query.toLowerCase()),
    );

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 className="text-lg font-semibold">
                    Playlists ({playlists.length})
                </h2>
                <div className="flex items-center gap-2">
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search playlists…"
                        className="w-48"
                    />
                    <CreatePlaylistDialog businessId={businessId} />
                </div>
            </div>
            {playlists.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        No playlists yet. Create one, then add videos to it.
                    </CardContent>
                </Card>
            ) : filtered.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        No playlists match “{query}”.
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {filtered.map((playlist) => (
                        <PlaylistCard
                            key={playlist.id}
                            playlist={playlist}
                            videos={videos}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}
