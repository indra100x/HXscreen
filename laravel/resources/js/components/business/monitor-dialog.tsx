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
const LIVE_POLL_MS = 10_000;

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
    const [syncNote, setSyncNote] = useState<string | null>(null);
    const [diag, setDiag] = useState('—');
    const videoRef = useRef<HTMLVideoElement>(null);
    const seekTo = useRef(0);

    // React does not reliably set the muted *property* before autoplay is
    // evaluated, and Firefox then blocks autoplay. Force it imperatively.
    const setVideoRef = (element: HTMLVideoElement | null) => {
        videoRef.current = element;
        if (element) {
            element.muted = true;
            element.defaultMuted = true;
        }
    };

    const {
        post: send,
        transform,
        processing: commanding,
    } = useHttp({ action: '', arg: 0 });

    // Plain fetch for the screen state: useHttp's GET response shape isn't
    // the raw JSON, and its transform would leak POST fields into the query.
    async function fetchLiveScreen(): Promise<Screen | null> {
        try {
            const res = await window.fetch(
                showScreen.url({ screen: screen.id }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            if (!res.ok) {
                return null;
            }
            const data = (await res.json()) as { screen?: Screen };
            return data.screen ?? null;
        } catch {
            return null;
        }
    }

    // The dialog tracks the TV itself, not the (up to 30s stale) page
    // props: pull fresh state on open and every 10s while open.
    const [liveScreen, setLiveScreen] = useState(screen);

    // Page props also refresh in the background, but they can be older
    // than the interval state — only accept them when newer, otherwise a
    // stale poll would un-pause a paused preview.
    useEffect(() => {
        setLiveScreen((prev) => {
            const prevAt =
                parseServerUtc(prev.position_reported_at)?.getTime() ?? 0;
            const nextAt =
                parseServerUtc(screen.position_reported_at)?.getTime() ?? 0;
            return nextAt >= prevAt ? screen : prev;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [screen]);

    useEffect(() => {
        if (!open) {
            return;
        }
        let cancelled = false;
        const pull = () => {
            void fetchLiveScreen().then((next) => {
                if (next && !cancelled) {
                    setLiveScreen(next);
                }
            });
        };
        pull();
        const timer = window.setInterval(pull, LIVE_POLL_MS);
        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const videos = orderedVideos(screen);
    const reportedAt = parseServerUtc(liveScreen.position_reported_at);
    const fresh =
        reportedAt != null &&
        Date.now() - reportedAt.getTime() < STALE_AFTER_MS;

    // Mirror the TV's own play state in the preview. Pausing is trusted
    // outright; resuming requires proof (a new video, or a position that
    // actually advanced), so a stale or glitchy `true` flag can never
    // restart a paused preview by itself.
    const lastSeen = useRef<{ videoId: string | null; ms: number } | null>(
        null,
    );

    useEffect(() => {
        const video = videoRef.current;
        if (!video || !open) {
            return;
        }
        const vid = liveScreen.current_video_id;
        const ms = liveScreen.current_position_ms ?? 0;
        const prev = lastSeen.current;
        lastSeen.current = { videoId: vid, ms };

        if (liveScreen.is_playing === false) {
            if (!video.paused) {
                video.pause();
            }
            return;
        }
        if (liveScreen.is_playing === true) {
            const switched = !prev || prev.videoId !== vid;
            const advanced =
                !!prev && prev.videoId === vid && ms > prev.ms + 2000;
            if ((switched || advanced) && video.paused) {
                void video.play().catch(() => {});
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [liveScreen.is_playing, liveScreen.position_reported_at]);

    // If the TV has moved on (different video, or drift beyond a few
    // seconds), pull the preview back to it. Small drifts are left alone
    // to avoid visible jumps.
    useEffect(() => {
        if (!open || videos.length === 0) {
            return;
        }
        const live = liveState(liveScreen, videos);
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
    }, [liveScreen.position_reported_at]);

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
                if (action === 'seek_by') {
                    // Move the preview instantly; the TV confirms via
                    // its next heartbeat and the follow effect.
                    const video = videoRef.current;
                    if (video && video.readyState >= 1) {
                        video.currentTime = Math.max(
                            0,
                            video.currentTime + arg,
                        );
                    }
                }
            },
            onError: toastApiError('Command failed'),
        });
    }

    // Pull the TV's current state on demand and apply it straight to
    // the element: switch video if needed, seek to the live frame, and
    // match the TV's play state. Imperative on purpose — the passive
    // mirror/follow effects skip paused elements and unchanged data,
    // which is exactly when an explicit re-sync must still act.
    // Always reports the outcome so the button never feels dead.
    function syncToLive() {
        setSyncNote('Syncing…');
        void fetchLiveScreen().then((next) => {
            if (!next) {
                setSyncNote('Sync failed — no response from server.');
                toast.error('Could not re-sync');
                return;
            }
            const live = liveState(next, videos);
            const target = Math.min(live.index, Math.max(0, videos.length - 1));
            const video = videoRef.current;
            const sameVideo = videos[target]?.id === videos[index]?.id;
            const drift =
                sameVideo && video ? live.at - video.currentTime : null;
            setLiveScreen(next);
            if (!sameVideo) {
                seekTo.current = live.at;
                setIndex(target);
                setSyncNote('Synced to live.');
                toast.success('Re-synced to live');
                return;
            }
            if (video) {
                try {
                    if (video.readyState >= 1) {
                        video.currentTime = live.at;
                    } else {
                        seekTo.current = live.at;
                    }
                } catch {
                    seekTo.current = live.at;
                }
                if (next.is_playing === false && !video.paused) {
                    video.pause();
                } else if (next.is_playing === true && video.paused) {
                    void video.play().catch(() => {});
                }
            }
            if (drift !== null && Math.abs(drift) > 2) {
                const seconds = Math.round(drift);
                setSyncNote(`Synced (${seconds > 0 ? '+' : ''}${seconds}s).`);
                toast.success(
                    `Re-synced (${seconds > 0 ? '+' : ''}${seconds}s)`,
                );
            } else {
                setSyncNote('Already in sync.');
                toast.success('Already in sync');
            }
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
        const report = () => {
            const err = video.error ? `err=${video.error.code}` : 'err=none';
            setDiag(
                `ready=${video.readyState} net=${video.networkState} paused=${video.paused} muted=${video.muted} ${err} src=${video.currentSrc.slice(-40)}`,
            );
        };
        const onTime = () => {
            setClock(video.currentTime);
            report();
        };
        const onEvent = () => report();
        const mediaEvents = [
            'play',
            'pause',
            'error',
            'stalled',
            'waiting',
            'canplay',
        ] as const;
        video.addEventListener('timeupdate', onTime);
        for (const name of mediaEvents) {
            video.addEventListener(name, onEvent);
        }
        report();
        const timer = window.setInterval(report, 2000);
        return () => {
            window.clearInterval(timer);
            video.removeEventListener('timeupdate', onTime);
            for (const name of mediaEvents) {
                video.removeEventListener(name, onEvent);
            }
        };
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
                        {liveScreen.is_playing === false ? (
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
                            ref={setVideoRef}
                            className="aspect-video w-full rounded-md bg-black"
                            src={
                                current.media_url ?? publicFileUrl(current.url)
                            }
                            muted
                            autoPlay
                            playsInline
                            onLoadedMetadata={(e) => {
                                e.currentTarget.currentTime = seekTo.current;
                            }}
                            onCanPlay={(e) => {
                                // Autoplay only when the TV itself is
                                // playing — otherwise every seek would
                                // restart a paused preview.
                                if (liveScreen.is_playing !== false) {
                                    e.currentTarget.play().catch(() => {});
                                }
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
                            <span
                                className="w-full font-mono text-[11px] text-muted-foreground"
                                title="Debug: element state"
                            >
                                {diag}
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
                        {syncNote && (
                            <p className="text-xs text-muted-foreground">
                                {syncNote}
                            </p>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
