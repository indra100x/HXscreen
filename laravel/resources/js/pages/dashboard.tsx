import { Head, Link, router, useHttp } from '@inertiajs/react';
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
import { dashboard } from '@/routes';
import { show } from '@/routes/business';
import { destroy, store } from '@/routes/businesses';
import type { Business } from '@/types';

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
            onError: () => toast.error('Could not create business'),
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

    function onDelete() {
        if (!window.confirm(`Delete "${business.name}" and all its content?`)) {
            return;
        }
        void remove(destroy.url({ business: business.id }), {
            onSuccess: () => {
                toast.success('Business deleted');
                router.reload();
            },
            onError: () => toast.error('Could not delete business'),
        });
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle>
                            <Link
                                href={show.url({ business: business.id })}
                                className="hover:underline"
                            >
                                {business.name}
                            </Link>
                        </CardTitle>
                        <CardDescription>
                            {business.screens_count ?? 0} screens ·{' '}
                            {business.playlists_count ?? 0} playlists ·{' '}
                            {business.videos_count ?? 0} videos
                        </CardDescription>
                    </div>
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
            </CardHeader>
            <CardContent>
                <Link href={show.url({ business: business.id })}>
                    <Button variant="outline" size="sm">
                        Manage
                    </Button>
                </Link>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ businesses }: { businesses: Business[] }) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Your businesses"
                        description="Pick a business to manage its screens, playlists and videos."
                    />
                    <CreateBusinessDialog />
                </div>

                {businesses.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                            <p className="font-medium">No businesses yet</p>
                            <p className="text-sm text-muted-foreground">
                                Create your first business to start managing
                                screens and content.
                            </p>
                            <Badge variant="secondary">Step 1 of 3</Badge>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {businesses.map((business) => (
                            <BusinessCard
                                key={business.id}
                                business={business}
                            />
                        ))}
                    </div>
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
