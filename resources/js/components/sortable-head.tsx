import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { TableHead } from '@/components/ui/table';
import { nextSort } from '@/hooks/use-table-sort';
import type { SortDirection, Sorter } from '@/hooks/use-table-sort';
import { cn } from '@/lib/utils';

/**
 * A column header that sorts its table: the first click sorts in `first`
 * direction, the second reverses it and the third restores the default order.
 */
export function SortableHead({
    column,
    sorter,
    first = 'asc',
    className,
    children,
    ...props
}: ComponentProps<typeof TableHead> & {
    column: string;
    sorter: Sorter;
    first?: SortDirection;
}) {
    const direction = sorter.sort === column ? sorter.direction : null;
    const right = className?.split(' ').includes('text-right') ?? false;
    const Icon =
        direction === 'asc'
            ? ArrowUp
            : direction === 'desc'
              ? ArrowDown
              : ChevronsUpDown;

    return (
        <TableHead
            aria-sort={
                direction === 'asc'
                    ? 'ascending'
                    : direction === 'desc'
                      ? 'descending'
                      : 'none'
            }
            data-sort={column}
            className={className}
            {...props}
        >
            <button
                type="button"
                onClick={() => sorter.onSort(nextSort(sorter, column, first))}
                className={cn(
                    '-mx-1 inline-flex h-7 items-center gap-1 rounded-sm px-1 font-medium whitespace-nowrap outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring',
                    right && 'flex-row-reverse',
                    direction && 'text-foreground',
                )}
            >
                {children}
                <Icon
                    aria-hidden
                    className={cn(
                        'size-3 shrink-0',
                        !direction && 'opacity-40',
                    )}
                />
            </button>
        </TableHead>
    );
}
