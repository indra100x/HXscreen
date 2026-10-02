import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
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
import { destroy, store, update } from '@/routes/videos';
import RenameDialog from '@/components/business/rename-dialog';
import { toastApiError } from '@/lib/api-errors';
import type { Video } from '@/types';
import { publicFileUrl } from '@/lib/media';

function UploadVideoDialog({ businessId }: { businessId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, progress, errors, reset } =
        useHttp({
            name: '',
            video: null as File | null,
            busniss_id: businessId,
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (data.video && data.video.size > 100 * 1024 * 1024) {
            toast.error('Video must be smaller than 100 MB.');
            return;
        }
        void post(store.url(), {
            onSuccess: () => {
                reset();
                setOpen(false);
                toast.success('Video uploaded');
                router.reload();
            },
            onError: toastApiError(
                'Upload failed. Check the file type and size.',
            ),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Upload video</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Upload video</DialogTitle>
                    <DialogDescription>
                        MP4, MOV, AVI or WebM up to 100 MB.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="video-name">
                            Name (optional — defaults to file name)
                        </Label>
                        <Input
                            id="video-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            maxLength={255}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="video-file">File</Label>
                        <Input
                            id="video-file"
                            type="file"
                            accept="video/mp4,video/quicktime,video/x-msvideo,video/webm"
                            onChange={(e) =>
                                setData('video', e.target.files?.[0] ?? null)
                            }
                            required
                        />
                        <InputError message={errors.video} />
                    </div>
                    {progress && (
                        <progress
                            className="w-full"
                            value={progress.percentage}
                            max={100}
                        >
                            {progress.percentage}%
                        </progress>
                    )}
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || !data.video}
                        >
                            {processing ? 'Uploading…' : 'Upload'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function VideoCard({ video }: { video: Video }) {
    const { delete: remove, processing } = useHttp();

    function onDelete() {
        if (!window.confirm(`Delete video "${video.name ?? video.url}"?`)) {
            return;
        }
        void remove(destroy.url({ video: video.id }), {
            onSuccess: () => {
                toast.success('Video deleted');
                router.reload();
            },
            onError: toastApiError('Could not delete video'),
        });
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <CardTitle className="truncate">
                            {video.name ?? 'Untitled'}
                        </CardTitle>
                        <CardDescription className="truncate font-mono text-xs">
                            {video.url}
                        </CardDescription>
                    </div>
                    <div className="flex shrink-0 items-center">
                        <RenameDialog
                            title="video"
                            currentName={video.name ?? ''}
                            url={update.url({ video: video.id })}
                            successMessage="Video renamed"
                            maxLength={255}
                        />
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onDelete}
                            disabled={processing}
                            className="shrink-0 text-destructive hover:text-destructive"
                        >
                            Delete
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-2">
                <video
                    className="aspect-video w-full rounded-md bg-black"
                    controls
                    preload="metadata"
                    src={publicFileUrl(video.url)}
                />
                <div className="text-sm text-muted-foreground">
                    Uploaded {new Date(video.created_at).toLocaleDateString()}
                </div>
            </CardContent>
        </Card>
    );
}

export default function VideosSection({
    businessId,
    videos,
}: {
    businessId: string;
    videos: Video[];
}) {
    const [query, setQuery] = useState('');
    const filtered = videos.filter((video) =>
        `${video.name ?? ''} ${video.url}`
            .toLowerCase()
            .includes(query.toLowerCase()),
    );

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 className="text-lg font-semibold">
                    Videos ({videos.length})
                </h2>
                <div className="flex items-center gap-2">
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search videos…"
                        className="w-48"
                    />
                    <UploadVideoDialog businessId={businessId} />
                </div>
            </div>
            {videos.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        No videos yet. Upload one to add it to playlists.
                    </CardContent>
                </Card>
            ) : filtered.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        No videos match “{query}”.
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {filtered.map((video) => (
                        <VideoCard key={video.id} video={video} />
                    ))}
                </div>
            )}
        </section>
    );
}
