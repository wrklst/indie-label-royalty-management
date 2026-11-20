import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

/**
 * A stored connection: what it is, with secrets cut down to a hint, and
 * a cross to disconnect. A new one can be entered once it is gone.
 */
export function ConnectionCard({
    title,
    facts,
    note,
    disconnect,
    disconnectLabel,
    confirm,
    children,
}: {
    children?: ReactNode;
    title: string;
    facts: [string, ReactNode][];
    note?: ReactNode;
    disconnect?: string;
    disconnectLabel?: string;
    confirm?: string;
}) {
    return (
        <div
            data-connection={title}
            className="flex items-start justify-between gap-4 rounded-xl border bg-card p-4"
        >
            <div className="min-w-0 space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{title}</span>
                    <Badge variant="success">Connected</Badge>
                </div>
                <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                    {facts.map(([label, value]) => (
                        <div key={label} className="contents">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="min-w-0 wrap-anywhere">{value}</dd>
                        </div>
                    ))}
                </dl>
                {note && (
                    <p className="text-xs text-muted-foreground">{note}</p>
                )}
                {children}
            </div>
            {disconnect && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title={disconnectLabel}
                    aria-label={disconnectLabel}
                    onClick={() =>
                        window.confirm(confirm) &&
                        router.delete(disconnect, { preserveScroll: true })
                    }
                >
                    <X />
                </Button>
            )}
        </div>
    );
}
