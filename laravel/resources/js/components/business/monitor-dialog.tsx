import { MonitorPlay, Pause, Play, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useHttp } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { toastApiError } from '@/lib/api-errors';
import { parseServerUtc } from '@/lib/time';
import { publicFileUrl } from '@/lib/media';
import { command as sendCommand, show as showScreen } from '@/routes/screens';
import type { Screen, Video } from '@/types';

const STALE_AFTER_MS = 2 * 60 * 1000;

function orderedVideos(screen: Screen): Video[] {
    return (screen.screen_playlists ?? []).flatMap((p) => p.videos ?? []);
}

/** Where the TV is (best effort): video index + seconds into it. */
function liveState(
    screen: Screen,
    videos: Video[],
): { index: number; at: number } {
    const index = videos.findIndex((v) => v.id === screen.current_video_id);
    if (
        index < 0 ||
        screen.current_position_ms == null ||
        !screen.position_reported_at
    ) {
        return { index: 0, at: 0 };
    }
    // A paused frame never moves: land exactly on it. Only advance the
    // estimate while the TV is actually playing.
    const playing = screen.is_playing !== false;
    const reported = parseServerUtc(screen.position_reported_at);
    const drift =
        playing && reported
            ? Math.max(0, (Date.now() - reported.getTime()) / 1000)
            : 0;
    return {
        index,
        at: Math.max(0, screen.current_position_ms / 1000 + drift),
    };
}

function formatTime(totalSeconds: number): string {
    const s = Math.max(0, Math.floor(totalSeconds));
    const m = Math.floor(s / 60);
    return `${m}:${String(s % 60).padStart(2, '0')}`;
}

type RemoteAction = 'pause' | 'resume' | 'seek_by';

export default function MonitorDialog({ screen }: { screen: Screen }) {
    const [open, setOpen] = useState(false);
    const [index, setIndex] = useState(0);
    const [clock, setClock] = useState(0);
    const [tvPaused, setTvPaused] = useState(false);
    const videoRef = useRef<HTMLVideoElement>(null);
    const seekTo = useRef(0);

    const videos = orderedVideos(screen);
    const reportedAt = parseServerUtc(screen.position_reported_at);
    const fresh =
        reportedAt != null &&
        Date.now() - reportedAt.getTime() < STALE_AFTER_MS;

    // Mirror the TV's own play state in the preview.
    useEffect(() => {
        const video = videoRef.current;
        if (!video || !open) {
            return;
        }
        if (screen.is_playing === false && !video.paused) {
            video.pause();
        } else if (screen.is_playing === true && video.paused) {
            void video.play().catch(() => {});
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [screen.is_playing, screen.position_reported_at]);

    // Props refresh every 30s via page polling: if the TV has moved on
    // (different video, or drift beyond a few seconds), pull the preview
    // back to it. Small drifts are left alone to avoid visible jumps.
    useEffect(() => {
        if (!open || videos.length === 0) {
            return;
        }
        const live = liveState(screen, videos);
        const target = Math.min(live.index, videos.length - 1);
        const video = videoRef.current;
        if (target !== index) {
            seekTo.current = live.at;
            setIndex(target);
            return;
        }
        if (
            video &&
            !video.paused &&
            Math.abs(video.currentTime - live.at) > 5
        ) {
            video.currentTime = live.at;
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [screen.position_reported_at]);

    const {
        post: send,
        get: fetch,
        transform,
        processing: commanding,
    } = useHttp({ action: '', arg: 0 });

    function remote(action: RemoteAction, arg: number, doneMessage: string) {
        transform(() => ({ action, arg }));
        void send(sendCommand.url({ screen: screen.id }), {
            onSuccess: () => {
                toast.success(doneMessage);
                if (action === 'pause') {
                    setTvPaused(true);
                }
                if (action === 'resume') {
                    setTvPaused(false);
                }
            },
            onError: toastApiError('Command failed'),
        });
    }

    /** Seek the preview to the given video/offset, remounting if needed. */
    const indexRef = useRef(index);
    indexRef.current = index;

    function goTo(index: number, at: number) {
        seekTo.current = at;
        const video = videoRef.current;
        if (index !== indexRef.current) {
            setIndex(index);
        } else if (video && video.readyState >= 1) {
            video.currentTime = at;
        }
    }

    function applyLiveState(liveScreen: Screen) {
        const live = liveState(liveScreen, videos);
        goTo(Math.min(live.index, Math.max(0, videos.length - 1)), live.at);
    }

    // Pull the TV's current state on demand (props can lag ~30s behind),
    // then land the preview on it.
    function syncToLive() {
        void fetch(showScreen.url({ screen: screen.id }), {
            onSuccess: (response) => {
                const liveScreen = (response as unknown as { screen: Screen })
                    .screen;
                if (liveScreen) {
                    applyLiveState(liveScreen);
                }
            },
            onError: toastApiError('Could not re-sync'),
        });
    }

    useEffect(() => {
        if (open) {
            syncToLive();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    useEffect(() => {
        const video = videoRef.current;
        if (!video) {
            return;
        }
        const onTime = () => setClock(video.currentTime);
        video.addEventListener('timeupdate', onTime);
        return () => video.removeEventListener('timeupdate', onTime);
    }, [index, open]);

    const current = videos[index];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <MonitorPlay className="size-4" />
                    Monitor
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {screen.name}
                        {screen.is_playing === false ? (
                            <Badge variant="secondary">TV paused</Badge>
                        ) : fresh ? (
                            <Badge>
                                <span className="relative mr-1 flex size-1.5">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                                    <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                                </span>
                                LIVE
                            </Badge>
                        ) : (
                            <Badge variant="outline">Preview</Badge>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {fresh
                            ? 'Preview follows the TV with a few seconds of delay. The controls below drive the TV itself.'
                            : 'TV position unknown — showing the playlist from the start.'}
                    </DialogDescription>
                </DialogHeader>
                {videos.length === 0 || !current ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        Nothing assigned to this screen.
                    </p>
                ) : (
                    <div className="space-y-3">
                        <video
                            key={`${current.id}-${index}`}
                            ref={videoRef}
                            className="aspect-video w-full rounded-md bg-black"
                            src={publicFileUrl(current.url)}
                            muted
                            autoPlay
                            onLoadedMetadata={(e) => {
                                e.currentTarget.currentTime = seekTo.current;
                            }}
                            onEnded={() => {
                                // Advance locally through the playlist loop.
                                seekTo.current = 0;
                                setIndex((i) => (i + 1) % videos.length);
                            }}
                        />
                        <div className="flex flex-wrap items-center gap-2">
                            {tvPaused ? (
                                <Button
                                    size="sm"
                                    disabled={commanding}
                                    onClick={() =>
                                        remote('resume', 0, 'TV resumed')
                                    }
                                >
                                    <Play className="size-4" />
                                    Resume TV
                                </Button>
                            ) : (
                                <Button
                                    size="sm"
                                    disabled={commanding}
                                    onClick={() =>
                                        remote('pause', 0, 'TV paused')
                                    }
                                >
                                    <Pause className="size-4" />
                                    Pause TV
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={commanding}
                                onClick={() =>
                                    remote('seek_by', -10, 'TV back 10s')
                                }
                            >
                                −10s
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={commanding}
                                onClick={() =>
                                    remote('seek_by', 10, 'TV forward 10s')
                                }
                            >
                                +10s
                            </Button>
                            <span className="text-sm text-muted-foreground">
                                {formatTime(clock)} ·{' '}
                                {current.name ?? current.url}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-auto"
                                onClick={syncToLive}
                            >
                                <RotateCcw className="size-4" />
                                Re-sync to live
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
