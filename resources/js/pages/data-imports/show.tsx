import { Link } from '@inertiajs/react';
import DataImportController from '@/actions/App/Http/Controllers/DataImportController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { Page, Panel, Stat } from '@/components/page';
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
import { useTableSort } from '@/hooks/use-table-sort';
import { date, month, quantity } from '@/lib/format';
import { index as catalog } from '@/routes/catalog';
import { create, index } from '@/routes/data-imports';
import { index as items } from '@/routes/items';
import { index as transactions } from '@/routes/transactions';
import { ImportStatusBadge } from './index';

type Row = {
    label: string;
    transactions: number;
    quantity: string;
    total: string;
};

type Props = {
    import: {
        id: number;
        name: string;
        importer_name: string;
        original_filename: string | null;
        status: 'pending' | 'imported' | 'failed' | 'deleted';
        rows_count: number;
        transactions_count: number;
        duplicates_count: number;
        errors_count: number;
        created_releases_count: number;
        created_tracks_count: number;
        needs_cleanup: boolean;
        total: string;
        errors: { line: number | null; message: string }[] | null;
        metadata: {
            skipped?: number;
            unassigned?: number;
            unconverted?: number;
        } | null;
        default_paid_timestamp: string | null;
        created_at: string;
        user: string | null;
    };
    unassignedItems: number;
    byRetailer: Row[];
    byFormat: Row[];
    byMonth: Row[];
};

const BREAKDOWN_COLUMNS = {
    label: (row: Row) => row.label,
    transactions: (row: Row) => row.transactions,
    quantity: (row: Row) => row.quantity,
    total: (row: Row) => row.total,
};

function Breakdown({
    title,
    rows,
    format,
}: {
    title: string;
    rows: Row[];
    format?: (label: string) => string;
}) {
    const { rows: sortedRows, sorter } = useTableSort(rows, BREAKDOWN_COLUMNS);

    return (
        <Panel title={title} flush>
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead column="label" sorter={sorter}>
                            {title
                                .replace('By ', '')
                                .replace(/^./, (letter) =>
                                    letter.toUpperCase(),
                                )}
                        </SortableHead>
                        <SortableHead
                            column="transactions"
                            sorter={sorter}
                            first="desc"
                            className="text-right"
                        >
                            Lines
                        </SortableHead>
                        <SortableHead
                            column="quantity"
                            sorter={sorter}
                            first="desc"
                            className="text-right"
                        >
                            Units
                        </SortableHead>
                        <SortableHead
                            column="total"
                            sorter={sorter}
                            first="desc"
                            className="text-right"
                        >
                            Total
                        </SortableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {sortedRows.map((row) => (
                        <TableRow key={row.label}>
                            <TableCell>
                                {format ? format(row.label) : row.label}
                            </TableCell>
                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                {row.transactions.toLocaleString('en-GB')}
                            </TableCell>
                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                {quantity(row.quantity)}
                            </TableCell>
                            <TableCell className="text-right">
                                <Amount value={row.total} precise />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </Panel>
    );
}

export default function ShowDataImport({
    import: dataImport,
    unassignedItems,
    byRetailer,
    byFormat,
    byMonth,
}: Props) {
    const failed = dataImport.status === 'failed';
    const skipped = dataImport.metadata?.skipped ?? 0;
    const unconverted = dataImport.metadata?.unconverted ?? 0;
    const unassigned = dataImport.metadata?.unassigned ?? 0;

    return (
        <Page
            title={dataImport.name}
            description={
                <span className="flex flex-wrap items-center gap-2">
                    <ImportStatusBadge
                        status={dataImport.status}
                        needsCleanup={dataImport.needs_cleanup}
                    />
                    {[
                        dataImport.importer_name,
                        dataImport.original_filename,
                        `uploaded ${date(dataImport.created_at.slice(0, 10))}${dataImport.user ? ` by ${dataImport.user}` : ''}`,
                        dataImport.default_paid_timestamp &&
                            `paid ${date(dataImport.default_paid_timestamp)}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
            }
            actions={
                <>
                    {dataImport.status === 'imported' && (
                        <Button variant="outline" asChild>
                            <Link
                                href={transactions({
                                    query: { import: dataImport.id },
                                })}
                            >
                                View transactions
                            </Link>
                        </Button>
                    )}
                    {dataImport.status !== 'deleted' && (
                        <DeleteButton
                            url={DataImportController.destroy.url(
                                dataImport.id,
                            )}
                            label="Delete import"
                            confirmLabel="Delete import and transactions"
                            title={`Delete ${dataImport.name}?`}
                            description={`${dataImport.transactions_count.toLocaleString('en-GB')} transaction(s) from this file are deleted. Releases and tracks it created stay in the catalog. Statements already generated keep their figures.`}
                        />
                    )}
                </>
            }
        >
            {failed && (
                <div className="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <p className="font-medium">Nothing was imported.</p>
                    <p className="mt-1">{dataImport.errors?.[0]?.message}</p>
                    <Button
                        variant="outline"
                        size="sm"
                        className="mt-3"
                        asChild
                    >
                        <Link href={create()}>Try again</Link>
                    </Button>
                </div>
            )}

            {!failed && (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat
                            label="Total imported"
                            value={<Amount value={dataImport.total} />}
                            hint={
                                <Amount
                                    value={dataImport.total}
                                    precise
                                    muted
                                />
                            }
                        />
                        <Stat
                            label="Transactions"
                            value={dataImport.transactions_count.toLocaleString(
                                'en-GB',
                            )}
                            hint={`of ${dataImport.rows_count.toLocaleString('en-GB')} lines in the file`}
                        />
                        <Stat
                            label="Skipped"
                            value={(
                                dataImport.duplicates_count + skipped
                            ).toLocaleString('en-GB')}
                            hint={`${dataImport.duplicates_count.toLocaleString('en-GB')} already imported, ${skipped.toLocaleString('en-GB')} not sales`}
                        />
                        <Stat
                            label="Added to catalog"
                            value={`${dataImport.created_releases_count} / ${dataImport.created_tracks_count}`}
                            hint="new releases / tracks"
                        />
                    </div>

                    {(unassigned > 0 ||
                        unconverted > 0 ||
                        dataImport.errors_count > 0) && (
                        <div className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            <p className="font-medium">To clean up</p>
                            <ul className="mt-1 grid gap-1">
                                {unassigned > 0 && (
                                    <li>
                                        {unassigned.toLocaleString('en-GB')}{' '}
                                        transaction(s) could not be matched to a
                                        release
                                        {unassignedItems > 0
                                            ? ` (${unassignedItems} item${unassignedItems === 1 ? '' : 's'})`
                                            : ''}
                                        .{' '}
                                        <Link
                                            href={items({
                                                query: { unassigned: 1 },
                                            })}
                                            className="underline underline-offset-4"
                                        >
                                            Assign items
                                        </Link>
                                    </li>
                                )}
                                {unconverted > 0 && (
                                    <li>
                                        {unconverted.toLocaleString('en-GB')}{' '}
                                        transaction(s) are in another currency
                                        and have no conversion rate, so they are
                                        not counted.{' '}
                                        <Link
                                            href={transactions({
                                                query: {
                                                    import: dataImport.id,
                                                    unconverted: 1,
                                                },
                                            })}
                                            className="underline underline-offset-4"
                                        >
                                            Show them
                                        </Link>
                                    </li>
                                )}
                                {dataImport.errors_count > 0 && (
                                    <li>
                                        {dataImport.errors_count.toLocaleString(
                                            'en-GB',
                                        )}{' '}
                                        line(s) could not be read and were left
                                        out. See below.
                                    </li>
                                )}
                            </ul>
                        </div>
                    )}

                    {dataImport.created_releases_count > 0 && (
                        <p className="text-sm text-muted-foreground">
                            New releases have no contract yet, so their income
                            stays with the label.{' '}
                            <Link
                                href={catalog()}
                                className="underline underline-offset-4"
                            >
                                Assign contracts in the catalog manager
                            </Link>
                            .
                        </p>
                    )}

                    <div className="grid items-start gap-6 lg:grid-cols-2 2xl:grid-cols-3">
                        <Breakdown title="By retailer" rows={byRetailer} />
                        <Breakdown title="By format" rows={byFormat} />
                        <Breakdown
                            title="By month"
                            rows={byMonth}
                            format={month}
                        />
                    </div>

                    {(dataImport.errors?.length ?? 0) > 0 && (
                        <Panel
                            title="Lines left out"
                            description={
                                dataImport.errors_count >
                                (dataImport.errors?.length ?? 0)
                                    ? `Showing the first ${dataImport.errors?.length} of ${dataImport.errors_count}.`
                                    : undefined
                            }
                            flush
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-24">
                                            Line
                                        </TableHead>
                                        <TableHead>Problem</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {dataImport.errors?.map(
                                        (error, position) => (
                                            <TableRow key={position}>
                                                <TableCell className="tabular-nums">
                                                    {error.line ?? '–'}
                                                </TableCell>
                                                <TableCell>
                                                    {error.message}
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                </TableBody>
                            </Table>
                        </Panel>
                    )}
                </>
            )}
        </Page>
    );
}

ShowDataImport.layout = {
    breadcrumbs: [
        { title: 'Data imports', href: index() },
        { title: 'Import', href: '#' },
    ],
};
