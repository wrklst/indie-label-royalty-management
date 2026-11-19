import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    ChevronsRight,
    CircleHelp,
    X,
} from 'lucide-react';
import { useEffect } from 'react';
import type { MouseEvent } from 'react';
import {
    closeHelp,
    handbookSlug,
    helpBack,
    minimizeHelp,
    openHelp,
    restoreHelp,
    topicFor,
    useHelp,
} from '@/components/help/help-store';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { handbook } from '@/routes';

/**
 * Opens the handbook article that explains the page being shown.
 */
export function HelpButton() {
    const { currentUrl } = useCurrentUrl();

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            title="Help for this page"
            aria-label="Help for this page"
            onClick={() => openHelp(topicFor(currentUrl))}
        >
            <CircleHelp />
        </Button>
    );
}

/**
 * The handbook beside the page: docked on the right on wide screens, laid
 * over the page on narrow ones. Links to other articles open in the panel.
 */
export function HelpPanel() {
    const article = useHelp((state) => state.article);
    const hasHistory = useHelp((state) => state.history.length > 0);
    const minimized = useHelp((state) => state.minimized);
    const loading = useHelp((state) => state.loading);
    const failed = useHelp((state) => state.failed);

    useEffect(restoreHelp, []);

    if (!article && !failed) {
        return null;
    }

    if (minimized) {
        return (
            <button
                type="button"
                onClick={() => minimizeHelp(false)}
                title={article?.title}
                aria-label="Show handbook"
                className="fixed top-1/2 right-0 z-40 flex -translate-y-1/2 flex-col items-center gap-2 rounded-l-lg border border-r-0 bg-background px-1.5 py-3 text-xs font-medium text-muted-foreground shadow-md hover:text-foreground"
            >
                <BookOpen className="size-4" />
                <span className="[writing-mode:vertical-rl]">Handbook</span>
            </button>
        );
    }

    const follow = (event: MouseEvent<HTMLDivElement>) => {
        const link = (event.target as HTMLElement).closest('a[href]');
        const slug = link
            ? handbookSlug(link.getAttribute('href') ?? '')
            : null;

        if (slug) {
            event.preventDefault();
            openHelp(slug);
        }
    };

    return (
        <aside
            aria-label="Handbook"
            data-help="panel"
            className="fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col border-l bg-background shadow-2xl lg:sticky lg:top-0 lg:z-auto lg:h-dvh lg:w-[26rem] lg:max-w-none lg:shrink-0 lg:shadow-none"
        >
            <div className="flex items-center gap-1 border-b px-3 py-3">
                {hasHistory && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Back"
                        aria-label="Back"
                        onClick={helpBack}
                    >
                        <ArrowLeft />
                    </Button>
                )}
                <div className="min-w-0 flex-1 px-1">
                    <div className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        {article?.chapter ?? 'Handbook'}
                    </div>
                    <div
                        className="truncate text-sm font-semibold"
                        data-help="title"
                    >
                        {article?.title}
                    </div>
                </div>
                <Button variant="ghost" size="icon" asChild>
                    <Link
                        href={handbook(article?.slug)}
                        title="Open in handbook"
                        aria-label="Open in handbook"
                    >
                        <BookOpen />
                    </Link>
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title="Minimise"
                    aria-label="Minimise handbook"
                    onClick={() => minimizeHelp(true)}
                >
                    <ChevronsRight />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title="Close"
                    aria-label="Close handbook"
                    onClick={closeHelp}
                >
                    <X />
                </Button>
            </div>
            <div
                className={cn(
                    'flex-1 overflow-y-auto px-5 py-4',
                    loading && 'opacity-50',
                )}
                onClick={follow}
            >
                {failed ? (
                    <p className="text-sm text-destructive">
                        This help article could not be loaded.
                    </p>
                ) : (
                    <div
                        className="handbook-prose"
                        dangerouslySetInnerHTML={{
                            __html: article?.html ?? '',
                        }}
                    />
                )}
            </div>
            <div className="border-t px-5 py-3 text-xs">
                <Link
                    href={handbook()}
                    className="text-muted-foreground underline hover:text-foreground"
                >
                    All chapters of the handbook
                </Link>
            </div>
        </aside>
    );
}
