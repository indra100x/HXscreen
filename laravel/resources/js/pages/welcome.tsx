import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Building2,
    CalendarClock,
    Check,
    ListVideo,
    QrCode,
    ShieldCheck,
    Tv,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: QrCode,
        title: 'Pair in seconds',
        description:
            'Each TV shows a 6-character code. Type it into the dashboard once and the screen is yours — secured with a rotating device token.',
    },
    {
        icon: ListVideo,
        title: 'Playlists in order',
        description:
            'Group videos into playlists, drag them into play order, and assign them to any screen. Changes go live on the next heartbeat.',
    },
    {
        icon: CalendarClock,
        title: 'Scheduled playback',
        description:
            'Give every assignment a time window. Morning promos, lunch menus, evening events — the right content plays at the right hour.',
    },
    {
        icon: Activity,
        title: 'Live device health',
        description:
            'Every screen checks in on a heartbeat. See at a glance which displays are online, offline, or still waiting to be paired.',
    },
    {
        icon: Building2,
        title: 'Multi-business ready',
        description:
            'Run signage for several venues or clients from one account. Each business keeps its own screens, playlists, and videos.',
    },
    {
        icon: ShieldCheck,
        title: 'Secure by default',
        description:
            'Hashed device tokens that expire and rotate, rate-limited pairing, and strict per-business access on every endpoint.',
    },
];

const steps = [
    {
        code: 'ABC123',
        title: 'TV shows a code',
        description:
            'Open the HXscreen player on any Android TV. It requests a pairing code and displays it on screen.',
    },
    {
        code: 'Pair',
        title: 'Enter it once',
        description:
            'In the dashboard, hit “Pair screen” and type the code. The TV receives its device token automatically.',
    },
    {
        code: 'Play',
        title: 'Assign & play',
        description:
            'Attach a playlist to the screen. The player pulls its content feed and starts playing — no USB sticks, ever.',
    },
];

function SiteNav({ authenticated }: { authenticated: boolean }) {
    return (
        <header className="sticky top-0 z-50 border-b border-border/60 bg-background/80 backdrop-blur">
            <nav className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-6">
                <Link
                    href="/"
                    className="flex items-center gap-2 font-semibold"
                >
                    <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <Tv className="size-4" />
                    </span>
                    HXscreen
                </Link>
                <div className="hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                    <a href="#features" className="hover:text-foreground">
                        Features
                    </a>
                    <a href="#how" className="hover:text-foreground">
                        How it works
                    </a>
                    <a href="#cta" className="hover:text-foreground">
                        Get started
                    </a>
                </div>
                <div className="flex items-center gap-2">
                    {authenticated ? (
                        <Button asChild>
                            <Link href={dashboard()}>
                                Open dashboard
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    ) : (
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>Get started</Link>
                            </Button>
                        </>
                    )}
                </div>
            </nav>
        </header>
    );
}

function Hero({ authenticated }: { authenticated: boolean }) {
    return (
        <section className="mx-auto grid w-full max-w-6xl items-center gap-12 px-6 py-20 lg:grid-cols-2 lg:py-28">
            <div className="space-y-6">
                <Badge variant="secondary">
                    Digital signage for modern businesses
                </Badge>
                <h1 className="text-4xl font-bold tracking-tight text-balance lg:text-6xl">
                    Every screen in your venue, managed from one place
                </h1>
                <p className="max-w-xl text-lg text-muted-foreground">
                    HXscreen turns any TV into a smart display. Pair devices
                    with a code, build playlists, schedule playback — and watch
                    every screen check in live from your dashboard.
                </p>
                <div className="flex flex-wrap gap-3">
                    <Button size="lg" asChild>
                        <Link href={authenticated ? dashboard() : register()}>
                            {authenticated ? 'Open dashboard' : 'Start free'}
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                    <Button size="lg" variant="outline" asChild>
                        <a href="#how">See how it works</a>
                    </Button>
                </div>
                <ul className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                    {[
                        'No hardware to buy',
                        'Pairing in seconds',
                        'Cancel anytime',
                    ].map((item) => (
                        <li key={item} className="flex items-center gap-2">
                            <Check className="size-4 text-primary" />
                            {item}
                        </li>
                    ))}
                </ul>
            </div>
            <div className="relative">
                <div className="absolute -inset-4 rounded-3xl bg-gradient-to-br from-primary/20 via-transparent to-primary/10 blur-2xl" />
                <Card className="relative overflow-hidden">
                    <CardHeader className="flex flex-row items-center justify-between gap-2 border-b pb-4">
                        <div className="flex items-center gap-2">
                            <span className="size-3 rounded-full bg-red-400" />
                            <span className="size-3 rounded-full bg-yellow-400" />
                            <span className="size-3 rounded-full bg-green-400" />
                        </div>
                        <Badge>2 of 3 screens online</Badge>
                    </CardHeader>
                    <CardContent className="space-y-3 pt-6">
                        {[
                            { name: 'Lobby TV', state: 'Online', live: true },
                            { name: 'Menu board', state: 'Online', live: true },
                            { name: 'Terrace', state: 'Pairing…', live: false },
                        ].map((screen) => (
                            <div
                                key={screen.name}
                                className="flex items-center justify-between gap-2 rounded-xl border px-4 py-3"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                        <Tv className="size-4" />
                                    </span>
                                    <div>
                                        <div className="text-sm font-medium">
                                            {screen.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Morning loop · 12 videos
                                        </div>
                                    </div>
                                </div>
                                <Badge
                                    variant={
                                        screen.live ? 'default' : 'outline'
                                    }
                                >
                                    <span className="mr-1.5 inline-block size-1.5 rounded-full bg-current" />
                                    {screen.state}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

function Features() {
    return (
        <section id="features" className="border-t bg-muted/40">
            <div className="mx-auto w-full max-w-6xl space-y-10 px-6 py-20">
                <div className="max-w-2xl space-y-3">
                    <Badge variant="outline">Features</Badge>
                    <h2 className="text-3xl font-bold tracking-tight">
                        Everything signage needs, nothing it doesn&apos;t
                    </h2>
                    <p className="text-muted-foreground">
                        Built for owners and operators — not AV technicians.
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <Card key={feature.title}>
                            <CardHeader>
                                <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <feature.icon className="size-5" />
                                </span>
                                <CardTitle className="pt-2">
                                    {feature.title}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CardDescription>
                                    {feature.description}
                                </CardDescription>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}

function HowItWorks() {
    return (
        <section id="how" className="border-t">
            <div className="mx-auto w-full max-w-6xl space-y-10 px-6 py-20">
                <div className="max-w-2xl space-y-3">
                    <Badge variant="outline">How it works</Badge>
                    <h2 className="text-3xl font-bold tracking-tight">
                        From unboxed TV to playing in minutes
                    </h2>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    {steps.map((step, index) => (
                        <Card key={step.title} className="relative">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <Badge variant="secondary">
                                        Step {index + 1}
                                    </Badge>
                                    <code className="rounded-md bg-muted px-2 py-1 font-mono text-sm">
                                        {step.code}
                                    </code>
                                </div>
                                <CardTitle className="pt-2">
                                    {step.title}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CardDescription>
                                    {step.description}
                                </CardDescription>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}

function Cta({ authenticated }: { authenticated: boolean }) {
    return (
        <section id="cta" className="border-t bg-muted/40">
            <div className="mx-auto w-full max-w-6xl px-6 py-20 text-center">
                <div className="mx-auto max-w-2xl space-y-6">
                    <h2 className="text-3xl font-bold tracking-tight">
                        Put your screens to work today
                    </h2>
                    <p className="text-muted-foreground">
                        Create an account, add your business, and pair your
                        first TV before lunch.
                    </p>
                    <Button size="lg" asChild>
                        <Link href={authenticated ? dashboard() : register()}>
                            {authenticated
                                ? 'Open dashboard'
                                : 'Create free account'}
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}

function SiteFooter() {
    return (
        <footer className="border-t">
            <div className="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground md:flex-row">
                <div className="flex items-center gap-2 font-semibold text-foreground">
                    <span className="flex size-7 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <Tv className="size-3.5" />
                    </span>
                    HXscreen
                </div>
                <p>
                    Digital signage for businesses. © {new Date().getFullYear()}{' '}
                    HXscreen.
                </p>
            </div>
        </footer>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;
    const authenticated = Boolean(auth.user);

    return (
        <>
            <Head title="Digital signage for your business" />
            <div className="min-h-screen bg-background text-foreground">
                <SiteNav authenticated={authenticated} />
                <main>
                    <Hero authenticated={authenticated} />
                    <Features />
                    <HowItWorks />
                    <Cta authenticated={authenticated} />
                </main>
                <SiteFooter />
            </div>
        </>
    );
}
