import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
    destroy as removeMember,
    store as inviteMember,
} from '@/routes/businesses/members';
import { toastApiError } from '@/lib/api-errors';

export type Member = {
    id: string;
    name: string;
    email: string;
};

function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function InviteDialog({ businessId }: { businessId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useHttp({ email: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void post(inviteMember.url({ business: businessId }), {
            onSuccess: () => {
                setData('email', '');
                setOpen(false);
                toast.success('Member invited');
                router.reload();
            },
            onError: toastApiError('Could not invite member'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Invite member</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite member</DialogTitle>
                    <DialogDescription>
                        The user needs an HXscreen account. Members can manage
                        everything except members and deletion.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="member-email">Email</Label>
                        <Input
                            id="member-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="teammate@example.com"
                            required
                        />
                        <InputError message={errors.email} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Invite
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function TeamSection({
    businessId,
    isOwner,
    members,
}: {
    businessId: string;
    isOwner: boolean;
    members: Member[];
}) {
    const { delete: remove, processing } = useHttp();

    function onRemove(member: Member) {
        if (!window.confirm(`Remove ${member.name} from this business?`)) {
            return;
        }
        void remove(
            removeMember.url({ business: businessId, member: member.id }),
            {
                onSuccess: () => {
                    toast.success('Member removed');
                    router.reload();
                },
                onError: toastApiError('Could not remove member'),
            },
        );
    }

    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-lg font-semibold">
                    Team ({members.length})
                </h2>
                {isOwner && <InviteDialog businessId={businessId} />}
            </div>
            {members.length === 0 ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        Only the owner has access.
                        {isOwner && ' Invite teammates to share the work.'}
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardContent className="divide-y p-0">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                className="flex items-center gap-3 px-4 py-3"
                            >
                                <Avatar className="size-8">
                                    <AvatarFallback>
                                        {initials(member.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-medium">
                                        {member.name}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {member.email}
                                    </div>
                                </div>
                                {isOwner && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        disabled={processing}
                                        onClick={() => onRemove(member)}
                                        className="text-destructive hover:text-destructive"
                                    >
                                        Remove
                                    </Button>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
            {!isOwner && (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">
                            You are a member
                        </CardTitle>
                        <CardDescription>
                            Members can manage screens, playlists, and videos.
                            Only the owner manages the team and deletes the
                            business.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}
        </section>
    );
}
