import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import DataImportController from '@/actions/App/Http/Controllers/DataImportController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { CheckField } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { EmptyState, Notice, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { SortableHead } from '@/components/sortable-head';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { serverSorter } from '@/hooks/use-table-sort';
import type { SortDirection } from '@/hooks/use-table-sort';
import { count, date } from '@/lib/format';
import type { Paginated } from '@/types';

export type ToolRun = {
    id: number;
    name: string;
    from: string;
    to: string;
    total: string;
    paid_on: string | null;
    transactions_count: number;
    created_at: string | null;
};

/** Drop trailing zeros from a decimal string: "0.131000" becomes "0.131". */
export const plain = (value: string) =>
    value.includes('.') ? value.replace(/\.?0+$/, '') : value;

export function OverlapNotice({ overlaps }: { overlaps: ToolRun[] }) {
    if (overlaps.length === 0) {
        return null;
    }

    return (
        <Notice>
            <p className="font-medium">
                This range overlaps an earlier run. Booking it again counts the
                same sales twice.
            </p>
            <ul className="list-disc pl-4">
                {overlaps.map((run) => (
                    <li key={run.id}>
                        <Link
                            href={DataImportController.show.url(run.id)}
                            className="underline"
                        >
                            {date(run.from)} – {date(run.to)}
                        </Link>
                    </li>
                ))}
            </ul>
        </Notice>
    );
}

/**
 * The dialog that books a report. The range and options it was calculated
 * with travel along as hidden fields, so the server books exactly what the
 * page shows.
 */
export function CreateTransactions({
    form,
    fields,
    overlaps,
    description,
    children,
}: {
    form: { action: string; method: 'post' };
    fields: Record<string, string | number | null>;
    overlaps: ToolRun[];
    description: ReactNode;
    children: (errors: Record<string, string>) => ReactNode;
}) {
    return (
        <FormDialog
            title="Book transactions"
            description={description}
            form={form}
            submitLabel="Book"
            trigger={
                <Button data-test="create-transactions">
                    Book transactions
                </Button>
            }
        >
            {(errors) => (
                <>
                    {Object.entries(fields).map(
                        ([name, value]) =>
                            value !== null &&
                            value !== '' && (
                                <input
                                    key={name}
                                    type="hidden"
                                    name={name}
                                    value={value}
                                />
                            ),
                    )}
                    {children(errors)}
                    {overlaps.length > 0 && (
                        <div className="grid gap-1.5">
                            <CheckField
                                id="confirm_overlap"
                                name="confirm_overlap"
                                value="1"
                                label="Book it although an earlier run overlaps this range"
                                hint="The same sales are then counted twice. Delete the earlier run instead if this one replaces it."
                            />
                            {errors.confirm_overlap && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {errors.confirm_overlap}
                                </p>
                            )}
                        </div>
                    )}
                    {errors.from && (
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {errors.from}
                        </p>
                    )}
                </>
            )}
        </FormDialog>
    );
}

/**
 * The earlier runs of a tool, as a page of uploaded files like the Data
 * imports list. Sorting keeps the report's own parameters in the address.
 */
export function RunsPanel({
    runs,
    filters,
    currency,
    totalLabel,
}: {
    runs: Paginated<ToolRun>;
    filters: { runs_sort: string | null; runs_direction: SortDirection | null };
    currency: string;
    totalLabel: string;
}) {
    const sorter = serverSorter(
        filters,
        ((changes: Record<string, string | null>) => {
            const url = new URL(window.location.href);

            url.searchParams.delete('runs_page');
            Object.entries(changes).forEach(([key, value]) =>
                value === null
                    ? url.searchParams.delete(key)
                    : url.searchParams.set(key, value),
            );
            router.get(
                url.pathname + url.search,
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }) as never,
        'runs',
    );

    return (
        <Panel
            title="Earlier runs"
            description="Each run is a data import holding the transactions it created. Deleting the import removes them again."
            flush
        >
            {runs.total === 0 ? (
                <EmptyState
                    compact
                    title="Nothing has been booked with this tool yet."
                />
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="covers"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Covers
                                </SortableHead>
                                <SortableHead
                                    column="paid_on"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Paid date
                                </SortableHead>
                                <SortableHead
                                    column="transactions_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Rows
                                </SortableHead>
                                <SortableHead
                                    column="total"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    {totalLabel}
                                </SortableHead>
                                <SortableHead
                                    column="created_at"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Uploaded
                                </SortableHead>
                                <TableHead className="w-12" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {runs.data.map((run) => (
                                <TableRow key={run.id}>
                                    <TableCell className="whitespace-nowrap">
                                        <Link
                                            href={DataImportController.show.url(
                                                run.id,
                                            )}
                                            className="font-medium hover:underline"
                                        >
                                            {date(run.from)} – {date(run.to)}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {date(run.paid_on)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count(run.transactions_count)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={run.total}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {date(run.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <DeleteButton
                                            url={DataImportController.destroy.url(
                                                run.id,
                                            )}
                                            title={`Delete run ${date(run.from)} – ${date(run.to)}?`}
                                            description={`The transactions it created (${count(run.transactions_count)}) are removed. Periods that included them change accordingly.`}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={runs} noun="runs" />
                </>
            )}
        </Panel>
    );
}
