import { Head, router, useHttp, usePoll } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    Clapperboard,
    ListVideo,
    MonitorPlay,
    Tv,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { dashboard } from '@/routes';
import { show } from '@/routes/business';
import { destroy, store } from '@/routes/businesses';
import { pair as pairScreen } from '@/routes/screens';
import { toastApiError } from '@/lib/api-errors';
import type { Business, Screen } from '@/types';

function CreateBusinessDialog() {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useHttp({ name: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(store.url(), {
            onSuccess: () => {
                setData('name', '');
                setOpen(false);
                toast.success('Business created');
                router.reload();
            },
            onError: toastApiError('Could not create business'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>New business</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New business</DialogTitle>
                    <DialogDescription>
                        Businesses group your screens, playlists and videos.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="business-name">Name</Label>
                        <Input
                            id="business-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Acme Inc."
                            required
                            maxLength={50}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating…' : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BusinessCard({ business }: { business: Business }) {
    const { delete: remove, processing } = useHttp();

    function onDelete(e: React.MouseEvent) {
        e.stopPropagation();
        if (!window.confirm(`Delete "${business.name}" and all its content?`)) {
            return;
        }
        void remove(destroy.url({ business: business.id }), {
            onSuccess: () => {
                toast.success('Business deleted');
                router.reload();
            },
            onError: toastApiError('Could not delete business'),
        });
    }

    function open() {
        router.visit(show.url({ business: business.id }));
    }

    const stats = [
        {
            icon: MonitorPlay,
            value: business.screens_count ?? 0,
            label: 'screens',
        },
        {
            icon: ListVideo,
            value: business.playlists_count ?? 0,
            label: 'playlists',
        },
        {
            icon: Clapperboard,
            value: business.videos_count ?? 0,
            label: 'videos',
        },
    ];

    return (
        <Card
            onClick={open}
            className="group cursor-pointer transition-all hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-lg"
        >
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                            <Building2 className="size-5" />
                        </span>
                        <div>
                            <CardTitle className="flex items-center gap-2 leading-tight">
                                {business.name}
                                {business.role === 'member' && (
                                    <Badge variant="secondary">Member</Badge>
                                )}
                            </CardTitle>
                            <CardDescription>
                                Updated{' '}
                                {new Date(
                                    business.updated_at,
                                ).toLocaleDateString()}
                            </CardDescription>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onDelete}
                        disabled={processing}
                        className="shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive"
                        hidden={business.role !== 'owner'}
                    >
                        Delete
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div className="flex items-center justify-between gap-2">
                    <div className="flex gap-4">
                        {stats.map((stat) => (
                            <span
                                key={stat.label}
                                className="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <stat.icon className="size-4" />
                                <span className="font-semibold text-foreground">
                                    {stat.value}
                                </span>
                                {stat.label}
                            </span>
                        ))}
                    </div>
                    <ArrowUpRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary" />
                </div>
            </CardContent>
        </Card>
    );
}

function PairRow({
    screen,
    businesses,
}: {
    screen: Screen;
    businesses: Business[];
}) {
    const { data, setData, post, processing, errors } = useHttp({
        device_id: screen.device_id,
        pairing_code: '',
        busniss_id: businesses.some((b) => b.id === screen.busniss_id)
            ? (screen.busniss_id as string)
            : (businesses[0]?.id ?? ''),
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(pairScreen.url(), {
            onSuccess: () => {
                toast.success(`“${screen.name}” paired`);
                router.reload();
            },
            onError: toastApiError('Pairing failed — check the code'),
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex flex-wrap items-center gap-2 rounded-xl border px-3 py-2"
        >
            <span className="flex min-w-0 flex-1 items-center gap-2">
                <Tv className="size-4 shrink-0 text-muted-foreground" />
                <span className="truncate text-sm font-medium">
                    {screen.name}
                </span>
                <span className="hidden truncate font-mono text-xs text-muted-foreground sm:inline">
                    {screen.device_id.slice(0, 8)}…
                </span>
            </span>
            {businesses.length > 1 && (
                <Select
                    value={data.busniss_id}
                    onValueChange={(value) => setData('busniss_id', value)}
                >
                    <SelectTrigger className="w-36">
                        <SelectValue placeholder="Business" />
                    </SelectTrigger>
                    <SelectContent>
                        {businesses.map((b) => (
                            <SelectItem key={b.id} value={b.id}>
                                {b.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
            <Input
                value={data.pairing_code}
                onChange={(e) => setData('pairing_code', e.target.value)}
                placeholder="Code from TV"
                required
                maxLength={20}
                className="w-32 font-mono uppercase"
            />
            <Button type="submit" size="sm" disabled={processing}>
                Pair
            </Button>
            {(errors.pairing_code ?? errors.device_id) && (
                <InputError message={errors.pairing_code ?? errors.device_id} />
            )}
        </form>
    );
}

function ReadyToPair({
    screens,
    businesses,
}: {
    screens: Screen[];
    businesses: Business[];
}) {
    if (screens.length === 0 || businesses.length === 0) {
        return null;
    }

    return (
        <Card className="border-dashed">
            <CardHeader className="pb-2">
                <CardTitle className="text-base">
                    Ready to pair ({screens.length})
                </CardTitle>
                <CardDescription>
                    These TVs asked for a pairing code. Type the code shown on
                    each TV — no need to enter device IDs.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                {screens.map((screen) => (
                    <PairRow
                        key={screen.id}
                        screen={screen}
                        businesses={businesses}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    businesses,
    unpairedScreens,
}: {
    businesses: Business[];
    unpairedScreens: Screen[];
}) {
    usePoll(20000);
    const totals = businesses.reduce(
        (acc, b) => ({
            screens: acc.screens + (b.screens_count ?? 0),
            playlists: acc.playlists + (b.playlists_count ?? 0),
            videos: acc.videos + (b.videos_count ?? 0),
        }),
        { screens: 0, playlists: 0, videos: 0 },
    );

    const overview = [
        { icon: Building2, value: businesses.length, label: 'Businesses' },
        { icon: MonitorPlay, value: totals.screens, label: 'Screens' },
        { icon: ListVideo, value: totals.playlists, label: 'Playlists' },
        { icon: Clapperboard, value: totals.videos, label: 'Videos' },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Your businesses"
                        description="Pick a business to manage its screens, playlists and videos."
                    />
                    <CreateBusinessDialog />
                </div>

                {businesses.length === 0 ? (
                    <Card className="overflow-hidden">
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <span className="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <Building2 className="size-7" />
                            </span>
                            <p className="text-lg font-semibold">
                                No businesses yet
                            </p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Create your first business to start pairing
                                screens and publishing content.
                            </p>
                            <div className="pt-2">
                                <CreateBusinessDialog />
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <ReadyToPair
                            screens={unpairedScreens}
                            businesses={businesses}
                        />
                        <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                            {overview.map((item) => (
                                <Card key={item.label}>
                                    <CardContent className="flex items-center gap-3 pt-6">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                            <item.icon className="size-5" />
                                        </span>
                                        <div>
                                            <div className="text-2xl leading-none font-bold">
                                                {item.value}
                                            </div>
                                            <div className="mt-1 text-sm text-muted-foreground">
                                                {item.label}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {businesses.map((business) => (
                                <BusinessCard
                                    key={business.id}
                                    business={business}
                                />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
