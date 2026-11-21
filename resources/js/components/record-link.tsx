import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { show as showArtist } from '@/routes/artists';
import { edit as editContract } from '@/routes/contracts';
import { show as showImport } from '@/routes/data-imports';
import { show as showPayee } from '@/routes/payees';
import { show as showRelease } from '@/routes/releases';
import { show as showTrack } from '@/routes/tracks';

/** How the name of a record looks as a link, also for a plain `Link` to its page. */
export const recordLinkClass =
    'underline decoration-muted-foreground/40 underline-offset-4 hover:decoration-current';

const PAGES = {
    artist: showArtist,
    contract: editContract,
    import: showImport,
    payee: showPayee,
    release: showRelease,
    track: showTrack,
};

/**
 * The name of a record as a link to its page. Without an id (a record
 * that is gone, or a name that never was one) the name is plain text,
 * so a table cell can always use it.
 */
export function RecordLink({
    to,
    id,
    children,
    className,
}: {
    to: keyof typeof PAGES;
    id: number | null | undefined;
    children: ReactNode;
    className?: string;
}) {
    if (id === null || id === undefined) {
        return <>{children}</>;
    }

    return (
        <Link href={PAGES[to](id)} className={cn(recordLinkClass, className)}>
            {children}
        </Link>
    );
}
