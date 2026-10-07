import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { CalendarClock } from 'lucide-react';
import InputError from '@/components/input-error';
import MonitorDialog from '@/components/business/monitor-dialog';
import RenameDialog from '@/components/business/rename-dialog';
import {
    formatServerUtc,
    localInputToServerUtc,
    serverUtcToLocalInput,
} from '@/lib/time';
import { Badge } from '@/components/ui/badge';
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
import { destroy, pair, store, update } from '@/routes/screens';
import {
    destroy as detachPlaylist,
    store as attachPlaylist,
    update as updatePlaylist,
} from '@/routes/screens/playlists';
import { toastApiError } from '@/lib/api-errors';
import type { Playlist, Screen } from '@/types';

function screenStatus(screen: Screen): {
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

function PairScreenDialog({
    businessId,
    unpairedScreens,
}: {
    businessId: string;
    unpairedScreens: Screen[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useHttp({
        device_id: '',
        pairing_code: '',
        busniss_id: businessId,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(pair.url(), {
            onSuccess: () => {
                setOpen(false);
                toast.success('Screen paired');
                router.reload();
            },
            onError: toastApiError('Pairing failed — check the code'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Pair screen</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pair a screen</DialogTitle>
                    <DialogDescription>
                        Enter the pairing code shown on the TV. The code expires
                        10 minutes after it is requested on the device.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {unpairedScreens.length > 0 && (
                        <div className="grid gap-2">
                            <Label>Waiting TVs — tap to fill</Label>
                            <div className="flex flex-wrap gap-2">
                                {unpairedScreens.map((screen) => (
                                    <Button
                                        key={screen.id}
                                        type="button"
                                        variant={
                                            data.device_id === screen.device_id
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() =>
                                            setData(
                                                'device_id',
                                                screen.device_id,
                                            )
                                        }
                                    >
                                        {screen.name}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="pair-device">Device ID</Label>
                        <Input
                            id="pair-device"
                            value={data.device_id}
                            onChange={(e) =>
                                setData('device_id', e.target.value)
                            }
                            placeholder="Shown on the TV"
                            required
                        />
                        <InputError message={errors.device_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="pair-code">Pairing code</Label>
                        <Input
                            id="pair-code"
                            value={data.pairing_code}
                            onChange={(e) =>
                                setData('pairing_code', e.target.value)
                            }
                            placeholder="ABC123"
                            required
                        />
                        <InputError message={errors.pairing_code} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Pairing…' : 'Pair'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AssignPlaylistDialog({
    screen,
    playlists,
}: {
    screen: Screen;
    playlists: Playlist[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, transform, processing, errors } = useHttp({
        playlist_id: '',
        start_time: '',
        end_time: '',
    });

    const assignedIds = new Set(
        (screen.screen_playlists ?? []).map((p) => p.id),
    );
    const available = playlists.filter((p) => !assignedIds.has(p.id));

    function submit(e: React.FormEvent) {
        e.preventDefault();
        transform((current) => ({
            ...current,
            start_time: localInputToServerUtc(current.start_time),
            end_time: localInputToServerUtc(current.end_time),
        }));
        void post(attachPlaylist.url({ screen: screen.id }), {
            onSuccess: () => {
                setOpen(false);
                toast.success('Playlist assigned');
                router.reload();
            },
            onError: toastApiError('Could not assign playlist'),
        });
    }

    if (available.length === 0) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Assign playlist
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assign playlist</DialogTitle>
                    <DialogDescription>
                        Choose a playlist to play on {screen.name}. Times use
                        your local timezone.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Select
                        value={data.playlist_id}
                        onValueChange={(value) => setData('playlist_id', value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select a playlist" />
                        </SelectTrigger>
                        <SelectContent>
                            {available.map((p) => (
                                <SelectItem key={p.id} value={p.id}>
                                    {p.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.playlist_id} />
                    <div className="grid gap-2">
                        <Label htmlFor="assign-start">
                            Start time (optional)
                        </Label>
                        <Input
                            id="assign-start"
                            type="datetime-local"
                            value={data.start_time}
                            onChange={(e) =>
                                setData('start_time', e.target.value)
                            }
                        />
                        <InputError message={errors.start_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assign-end">End time (optional)</Label>
                        <Input
                            id="assign-end"
                            type="datetime-local"
                            value={data.end_time}
                            onChange={(e) =>
                                setData('end_time', e.target.value)
                            }
                        />
                        <InputError message={errors.end_time} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || !data.playlist_id}
                        >
                            Assign
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditScheduleDialog({
    screenId,
    playlist,
}: {
    screenId: string;
    playlist: Playlist;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, put, transform, processing, errors } = useHttp({
        playlist_id: playlist.id,
        start_time: serverUtcToLocalInput(playlist.pivot?.start_time),
        end_time: serverUtcToLocalInput(playlist.pivot?.end_time),
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        transform((current) => ({
            ...current,
            start_time: localInputToServerUtc(current.start_time),
            end_time: localInputToServerUtc(current.end_time),
        }));
        void put(updatePlaylist.url({ screen: screenId }), {
            onSuccess: () => {
                setOpen(false);
                toast.success('Schedule updated');
                router.reload();
            },
            onError: toastApiError('Could not update schedule'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" title="Edit schedule">
                    <CalendarClock className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Schedule “{playlist.name}”</DialogTitle>
                    <DialogDescription>
                        Leave empty to play without a time window. Times use
                        your local timezone.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`sched-start-${playlist.id}`}>
                            Start time
                        </Label>
                        <Input
                            id={`sched-start-${playlist.id}`}
                            type="datetime-local"
                            value={data.start_time}
                            onChange={(e) =>
                                setData('start_time', e.target.value)
                            }
                        />
                        <InputError message={errors.start_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`sched-end-${playlist.id}`}>
                            End time
                        </Label>
                        <Input
                            id={`sched-end-${playlist.id}`}
                            type="datetime-local"
                            value={data.end_time}
                            onChange={(e) =>
                                setData('end_time', e.target.value)
                            }
                        />
                        <InputError message={errors.end_time} />
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

function PlaybackModeToggle({ screen }: { screen: Screen }) {
    const { put, transform, processing } = useHttp({ playback_mode: '' });

    function setMode(mode: 'loop' | 'once') {
        transform(() => ({ playback_mode: mode }));
        void put(update.url({ screen: screen.id }), {
            onSuccess: () => {
                toast.success(
                    mode === 'loop'
                        ? 'Screen loops the playlist'
                        : 'Screen plays once, then idles',
                );
                router.reload();
            },
            onError: toastApiError('Could not change playback mode'),
        });
    }

    const mode = screen.playback_mode ?? 'loop';

    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">Plays:</span>
            <div className="flex overflow-hidden rounded-md border">
                {(['loop', 'once'] as const).map((option) => (
                    <Button
                        key={option}
                        type="button"
                        variant={mode === option ? 'secondary' : 'ghost'}
                        size="sm"
                        disabled={processing}
                        onClick={() => setMode(option)}
                        className="rounded-none capitalize"
                    >
                        {option === 'loop' ? 'Loop' : 'Once'}
                    </Button>
                ))}
            </div>
        </div>
    );
}

function ScreenCard({
    screen,
    playlists,
}: {
    screen: Screen;
    playlists: Playlist[];
}) {
    const { delete: remove, processing } = useHttp();
    const status = screenStatus(screen);

    function onDelete() {
        if (!window.confirm(`Delete screen "${screen.name}"?`)) {
            return;
        }
        void remove(destroy.url({ screen: screen.id }), {
            onSuccess: () => {
                toast.success('Screen deleted');
                router.reload();
            },
            onError: toastApiError('Could not delete screen'),
        });
    }

    function onDetach(playlistId: string) {
        void remove(
            detachPlaylist.url(
                { screen: screen.id },
                { query: { playlist_id: playlistId } },
            ),
            {
                onSuccess: () => {
                    toast.success('Playlist removed');
                    router.reload();
                },
                onError: toastApiError('Could not remove playlist'),
            },
        );
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            {screen.name}
                            <Badge variant={status.variant}>
                                {status.live && (
                                    <span className="relative mr-1 flex size-1.5">
                                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                                        <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                                    </span>
                                )}
                                {status.label}
                            </Badge>
                        </CardTitle>
                        <CardDescription className="font-mono text-xs">
                            {screen.device_id}
                        </CardDescription>
                    </div>
                    <div className="flex shrink-0 items-center">
                        <RenameDialog
                            title="screen"
                            currentName={screen.name}
                            url={update.url({ screen: screen.id })}
                            successMessage="Screen renamed"
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
                <div className="text-sm text-muted-foreground">
                    Last seen:{' '}
                    {screen.last_seen_at
                        ? new Date(screen.last_seen_at).toLocaleString()
                        : 'never'}
                </div>
                <PlaybackModeToggle screen={screen} />
                {(screen.screen_playlists ?? []).length > 0 && (
                    <ul className="space-y-1">
                        {(screen.screen_playlists ?? []).map((p) => (
                            <li
                                key={p.id}
                                className="flex items-center justify-between gap-2 rounded-md border px-2 py-1 text-sm"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate">
                                        {p.name}
                                    </span>
                                    {(p.pivot?.start_time ||
                                        p.pivot?.end_time) && (
                                        <span className="block text-xs text-muted-foreground">
                                            {p.pivot.start_time
                                                ? formatServerUtc(
                                                      p.pivot.start_time,
                                                  )
                                                : '…'}
                                            {' → '}
                                            {p.pivot.end_time
                                                ? formatServerUtc(
                                                      p.pivot.end_time,
                                                  )
                                                : '…'}
                                        </span>
                                    )}
                                </span>
                                <div className="flex shrink-0">
                                    <EditScheduleDialog
                                        screenId={screen.id}
                                        playlist={p}
                                    />
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onDetach(p.id)}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="flex flex-wrap gap-2">
                    <MonitorDialog screen={screen} />
                    <AssignPlaylistDialog
                        screen={screen}
                        playlists={playlists}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function CreateScreenDialog({ businessId }: { businessId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useHttp({
        name: '',
        device_id: '',
        busniss_id: businessId,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(store.url(), {
            onSuccess: () => {
                setOpen(false);
                toast.success('Screen added — pair it from the TV');
                router.reload();
            },
            onError: toastApiError('Could not add screen'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Add screen manually</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add screen</DialogTitle>
                    <DialogDescription>
                        Register a screen before pairing it. Most of the time
                        you only need “Pair screen”.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="screen-name">Name</Label>
                        <Input
                            id="screen-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            maxLength={50}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="screen-device">Device ID</Label>
                        <Input
                            id="screen-device"
                            value={data.device_id}
                            onChange={(e) =>
                                setData('device_id', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.device_id} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ScreensSection({
    businessId,
    screens,
    playlists,
    unpairedScreens,
}: {
    businessId: string;
    screens: Screen[];
    playlists: Playlist[];
    unpairedScreens: Screen[];
}) {
    const [query, setQuery] = useState('');
    const filtered = screens.filter((screen) =>
        `${screen.name} ${screen.device_id}`
            .toLowerCase()
            .includes(query.toLowerCase()),
    );

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 className="text-lg font-semibold">
                    Screens ({screens.length})
                </h2>
                <div className="flex items-center gap-2">
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search screens…"
                        className="w-48"
                    />
                    <CreateScreenDialog businessId={businessId} />
                    <PairScreenDialog
                        businessId={businessId}
                        unpairedScreens={unpairedScreens}
                    />
                </div>
            </div>
            {screens.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        No screens yet. Pair your first TV to get started.
                    </CardContent>
                </Card>
            ) : filtered.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        No screens match “{query}”.
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {filtered.map((screen) => (
                        <ScreenCard
                            key={screen.id}
                            screen={screen}
                            playlists={playlists}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}
