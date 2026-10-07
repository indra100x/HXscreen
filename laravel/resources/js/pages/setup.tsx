import { Head, router, useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { dashboard } from '@/routes';
import { store as storeBusiness } from '@/routes/businesses';
import { toastApiError } from '@/lib/api-errors';
import AppLogoIcon from '@/components/app-logo-icon';

export default function Setup() {
    const { data, setData, post, processing, errors } = useHttp({ name: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(storeBusiness.url(), {
            onSuccess: () => {
                toast.success('Venue created — welcome!');
                router.visit(dashboard.url());
            },
            onError: toastApiError('Could not create your venue'),
        });
    }

    return (
        <>
            <Head title="Set up your venue" />
            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-6" />
                            </span>
                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-medium">
                                    Name your venue
                                </h1>
                                <p className="text-center text-sm text-muted-foreground">
                                    One panel, one venue. You can rename it
                                    anytime.
                                </p>
                            </div>
                        </div>
                        <Card>
                            <CardHeader>
                                <CardTitle>Your business</CardTitle>
                                <CardDescription>
                                    Screens, playlists, and team all live under
                                    this name.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submit} className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="venue-name">
                                            Venue name
                                        </Label>
                                        <Input
                                            id="venue-name"
                                            value={data.name}
                                            onChange={(e) =>
                                                setData('name', e.target.value)
                                            }
                                            placeholder="Acme Inc."
                                            required
                                            maxLength={50}
                                            autoFocus
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={
                                            processing ||
                                            data.name.trim() === ''
                                        }
                                    >
                                        {processing
                                            ? 'Creating…'
                                            : 'Create my venue'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
