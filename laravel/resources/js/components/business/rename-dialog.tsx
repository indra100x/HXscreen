import { router, useHttp } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toastApiError } from '@/lib/api-errors';

export default function RenameDialog({
    title,
    currentName,
    url,
    successMessage,
    maxLength = 50,
}: {
    title: string;
    currentName: string;
    url: string;
    successMessage: string;
    maxLength?: number;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, put, processing, errors } = useHttp({
        name: currentName,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        void put(url, {
            onSuccess: () => {
                setOpen(false);
                toast.success(successMessage);
                router.reload();
            },
            onError: toastApiError('Could not rename'),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" title={`Rename ${title}`}>
                    <Pencil className="size-3.5" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rename {title}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`rename-${title}`}>Name</Label>
                        <Input
                            id={`rename-${title}`}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            maxLength={maxLength}
                        />
                        <InputError message={errors.name} />
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
