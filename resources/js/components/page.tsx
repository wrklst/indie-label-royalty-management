import { Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Standard page frame: document title, heading, actions and content.
 */
export function Page({
    title,
    description,
    actions,
    children,
    className,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <>
            <Head title={title} />
            <div className={cn('flex flex-col gap-6 p-4 md:p-6', className)}>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description && (
                            <p className="max-w-3xl text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && (
                        <div className="flex flex-wrap items-center gap-2">
                            {actions}
                        </div>
                    )}
                </div>
                {children}
            </div>
        </>
    );
}

export function Panel({
    title,
    description,
    actions,
    children,
    className,
    flush = false,
}: {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    flush?: boolean;
}) {
    return (
        <section
            className={cn(
                'rounded-xl border bg-card text-card-foreground',
                className,
            )}
        >
            {(title || actions) && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                    <div className="space-y-0.5">
                        <h2 className="text-sm font-semibold">{title}</h2>
                        {description && (
                            <p className="text-xs text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions}
                </div>
            )}
            <div className={flush ? '' : 'p-4'}>{children}</div>
        </section>
    );
}

export function Stat({
    label,
    value,
    hint,
    tone,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    tone?: 'negative' | 'positive';
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div
                className={cn(
                    'mt-1 text-2xl font-semibold tracking-tight tabular-nums',
                    tone === 'negative' && 'text-red-600 dark:text-red-400',
                    tone === 'positive' &&
                        'text-emerald-600 dark:text-emerald-400',
                )}
            >
                {value}
            </div>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}

/**
 * Something that needs attention but did not fail: the amber box. `action`
 * sits on the right, for the button that deals with it.
 */
export function Notice({
    title,
    children,
    action,
}: {
    title?: ReactNode;
    children?: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div
            role="status"
            className="flex items-start gap-2.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
            <div className="min-w-0 flex-1 space-y-1">
                {title && <p className="font-medium">{title}</p>}
                {children}
            </div>
            {action}
        </div>
    );
}

/**
 * Nothing to list. `compact` is for a panel that already has a frame: the
 * text alone, without a box of its own.
 */
export function EmptyState({
    title,
    description,
    action,
    compact = false,
}: {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    compact?: boolean;
}) {
    if (compact) {
        return (
            <p className="p-4 text-sm text-muted-foreground">
                {title}
                {description && <> {description}</>}
                {action && <span className="ml-2">{action}</span>}
            </p>
        );
    }

    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center">
            <div className="space-y-1">
                <p className="text-sm font-medium">{title}</p>
                {description && (
                    <p className="mx-auto max-w-md text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}
