import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Delete with a confirmation that says what will be lost.
 */
export function DeleteButton({
    url,
    title,
    description,
    label,
    confirmLabel = 'Delete',
    alternative,
}: {
    /** A second, further-reaching way to delete, offered beside the first. */
    alternative?: { label: string; url: string };
    url: string;
    title: string;
    description: ReactNode;
    label?: string;
    confirmLabel?: string;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const remove = (target: string) =>
        router.delete(target, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {label ? (
                    <Button variant="outline" size="sm">
                        <Trash2 />
                        {label}
                    </Button>
                ) : (
                    <Button variant="ghost" size="icon" aria-label={title}>
                        <Trash2 className="text-muted-foreground" />
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className={alternative ? 'sm:max-w-xl' : undefined}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex-wrap gap-y-2">
                    <Button variant="secondary" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant={alternative ? 'outline' : 'destructive'}
                        disabled={processing}
                        onClick={() => remove(url)}
                    >
                        {confirmLabel}
                    </Button>
                    {alternative && (
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => remove(alternative.url)}
                        >
                            {alternative.label}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
