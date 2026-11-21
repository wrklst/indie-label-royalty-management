import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { DeleteButton } from '@/components/delete-button';
import { Panel } from '@/components/page';
import { Badge } from '@/components/ui/badge';

type Status = { label: string; variant: 'success' | 'warning' | 'outline' };

/**
 * The one card for everything a label connects or switches on: a
 * service, a login or the file storage. It says what it is and what it
 * is for, shows its state, and holds its switch, its options and its
 * credentials as children. Secrets are only ever shown as a hint.
 */
export function ServiceCard({
    title,
    kind,
    description,
    status,
    facts = [],
    note,
    disconnect,
    disconnectLabel,
    confirm,
    children,
}: {
    title: string;
    /** What kind of service it is, e.g. "Distributor". */
    kind?: string;
    /** What it is for, as a sentence. */
    description: ReactNode;
    status?: Status;
    facts?: [string, ReactNode][];
    note?: ReactNode;
    disconnect?: string;
    disconnectLabel?: string;
    /** What disconnecting removes, shown in the confirmation. */
    confirm?: ReactNode;
    children?: ReactNode;
}) {
    return (
        <div data-connection={title}>
            <Panel
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {title}
                        {kind && <Badge variant="outline">{kind}</Badge>}
                        {status && (
                            <Badge variant={status.variant}>
                                {status.label}
                            </Badge>
                        )}
                    </span>
                }
                description={description}
                actions={
                    disconnect && (
                        <DeleteButton
                            url={disconnect}
                            title={`Disconnect ${title}?`}
                            description={confirm}
                            confirmLabel="Disconnect"
                            triggerLabel={disconnectLabel}
                            icon={<X />}
                        />
                    )
                }
            >
                <div className="space-y-4">
                    {facts.length > 0 && (
                        <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                            {facts.map(([label, value]) => (
                                <div key={label} className="contents">
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="min-w-0 wrap-anywhere">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                    {note && (
                        <p className="text-xs text-muted-foreground">{note}</p>
                    )}
                    {children}
                </div>
            </Panel>
        </div>
    );
}
