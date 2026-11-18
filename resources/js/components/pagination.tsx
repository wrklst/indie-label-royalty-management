import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

export function Pagination({
    page,
    noun = 'rows',
}: {
    page: Paginated<unknown>;
    noun?: string;
}) {
    if (page.total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4 border-t px-3 py-2 text-xs text-muted-foreground">
            <span>
                {page.from?.toLocaleString('en-GB')}–
                {page.to?.toLocaleString('en-GB')} of{' '}
                {page.total.toLocaleString('en-GB')} {noun}
            </span>
            {page.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <span>
                        Page {page.current_page} of {page.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        asChild={!!page.prev_page_url}
                        disabled={!page.prev_page_url}
                    >
                        {page.prev_page_url ? (
                            <Link href={page.prev_page_url} preserveScroll>
                                Previous
                            </Link>
                        ) : (
                            'Previous'
                        )}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        asChild={!!page.next_page_url}
                        disabled={!page.next_page_url}
                    >
                        {page.next_page_url ? (
                            <Link href={page.next_page_url} preserveScroll>
                                Next
                            </Link>
                        ) : (
                            'Next'
                        )}
                    </Button>
                </div>
            )}
        </div>
    );
}
