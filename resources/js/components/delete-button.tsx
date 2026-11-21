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
 * Delete with a confirmation that says what will be lost. Every destructive
 * action asks here, titled "Delete {name}?"; when the server would refuse the
 * delete, pass its reason as `blocked` and the button is disabled with it.
 */
export function DeleteButton({
    url,
    title,
    description,
    label,
    confirmLabel = 'Delete',
    alternative,
    blocked,
    icon,
    triggerLabel,
}: {
    /** Why the server would refuse this delete; the button is disabled and shows it. */
    blocked?: string | null;
    /** Replaces the bin on the icon button, e.g. a cross to disconnect. */
    icon?: ReactNode;
    /** The accessible name of the icon button; the title when omitted. */
    triggerLabel?: string;
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

    if (blocked) {
        return (
            <span title={blocked} className="inline-flex">
                {label ? (
                    <Button variant="outline" size="sm" disabled>
                        <Trash2 />
                        {label}
                    </Button>
                ) : (
                    <Button
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label={triggerLabel ?? title}
                    >
                        {icon ?? <Trash2 className="text-muted-foreground" />}
                    </Button>
                )}
                <span className="sr-only">{blocked}</span>
            </span>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {label ? (
                    <Button variant="outline" size="sm">
                        <Trash2 />
                        {label}
                    </Button>
                ) : (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={triggerLabel ?? title}
                        title={triggerLabel}
                    >
                        {icon ?? <Trash2 className="text-muted-foreground" />}
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
