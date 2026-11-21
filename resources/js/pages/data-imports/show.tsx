import { MoneyFlow } from '@/components/money-flow';
import type { Flow } from '@/components/money-flow';
import { Form, Link, setLayoutProps } from '@inertiajs/react';
import DataImportController from '@/actions/App/Http/Controllers/DataImportController';
import { Amount } from '@/components/amount';
import { IncomeReport } from '@/components/income-report';
import type { Report } from '@/components/income-report';
import { DeleteButton } from '@/components/delete-button';
import { SelectInput } from '@/components/field';
import InputError from '@/components/input-error';
import { Notice, Page, Panel, Stat } from '@/components/page';
import { SortableHead } from '@/components/sortable-head';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableSort } from '@/hooks/use-table-sort';
import { count, date, plural } from '@/lib/format';
import { index as catalog } from '@/routes/catalog';
import { create, index } from '@/routes/data-imports';
import { index as items } from '@/routes/items';
import { index as transactions } from '@/routes/transactions';
import { ImportStatusBadge } from './index';

type Props = {
    import: {
        id: number;
        name: string;
        importer_name: string;
        original_filename: string | null;
        status: 'pending' | 'imported' | 'review' | 'failed' | 'deleted';
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
            duplicates?: Duplicate[];
            resolved_duplicates?: (Duplicate & { decision: string })[];
            skipped?: number;
            unassigned?: number;
            unconverted?: number;
        } | null;
        default_paid_timestamp: string | null;
        created_at: string;
        user: string | null;
    };
    unassignedItems: number;
    flow: Flow;
    report: Report;
};

type Duplicate = {
    key: string;
    kind: 'sum' | 'line';
    release: string | null;
    existing: {
        id: number;
        paid: string;
        total: string;
        memo: string | null;
        source: string | null;
    }[];
    existing_total: string;
    imported: { count: number; total: string; tracks: string[] };
};

const DECISIONS: Record<string, string> = {
    replace:
        'Merge: keep the lines of this file, remove the existing transaction',
    skip: 'Leave these lines of the file out, keep the existing transaction',
    both: 'Not a duplicate: keep both',
};

function DuplicateSides({ duplicate }: { duplicate: Duplicate }) {
    return (
        <div className="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <p className="text-xs font-medium text-muted-foreground uppercase">
                    Already booked
                </p>
                <ul className="mt-1 grid gap-1">
                    {duplicate.existing.map((transaction) => (
                        <li key={transaction.id}>
                            <Amount value={transaction.total} /> · paid{' '}
                            {date(transaction.paid)}
                            {transaction.source && ` · ${transaction.source}`}
                            {transaction.memo && (
                                <p className="text-xs text-muted-foreground">
                                    {transaction.memo}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
            <div>
                <p className="text-xs font-medium text-muted-foreground uppercase">
                    In this file
                </p>
                <p className="mt-1">
                    <Amount value={duplicate.imported.total} /> in{' '}
                    {plural(duplicate.imported.count, 'line')}
                </p>
                {duplicate.imported.tracks.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        {duplicate.imported.tracks.join(', ')}
                    </p>
                )}
            </div>
        </div>
    );
}

/**
 * An import that was held: nothing is booked until each possible duplicate is decided.
 */
function DuplicateReview({
    importId,
    duplicates,
}: {
    importId: number;
    duplicates: Duplicate[];
}) {
    return (
        <Panel
            title={`Possible duplicates (${duplicates.length})`}
            description="Lines of this file look like transactions that exist already, so nothing was imported yet. A merge keeps the detailed lines of the file and removes the earlier transaction; the lines stay unpublished if the transaction was, and take over its contract, payee and ledger."
        >
            <Form
                {...DataImportController.resolve.form(importId)}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        {duplicates.map((duplicate) => (
                            <fieldset
                                key={duplicate.key}
                                className="grid gap-3 rounded-lg border p-3"
                            >
                                <legend className="px-1 text-sm font-medium">
                                    {duplicate.release ?? 'Release'} ·{' '}
                                    {duplicate.kind === 'sum'
                                        ? 'same total in the month'
                                        : 'same amount on the same day'}
                                </legend>
                                <DuplicateSides duplicate={duplicate} />
                                <SelectInput
                                    name={`decisions[${duplicate.key}]`}
                                    defaultValue="replace"
                                    aria-label={`Decision for ${duplicate.release ?? 'the release'}`}
                                >
                                    {Object.entries(DECISIONS).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </SelectInput>
                            </fieldset>
                        ))}
                        <InputError message={errors.decisions} />
                        <div>
                            <Button disabled={processing}>
                                Import with these decisions
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </Panel>
    );
}

const ERROR_COLUMNS = {
    line: (row: { line: number | null; message: string }) => row.line,
    message: (row: { line: number | null; message: string }) => row.message,
};

const NO_ERRORS: { line: number | null; message: string }[] = [];

export default function ShowDataImport({
    import: dataImport,
    unassignedItems,
    flow,
    report,
}: Props) {
    // At most 200 lines are kept, so all of them are here and sort in the browser.
    const { rows: errorRows, sorter: errorSorter } = useTableSort(
        dataImport.errors ?? NO_ERRORS,
        ERROR_COLUMNS,
    );
    const failed = dataImport.status === 'failed';
    const review = dataImport.status === 'review';
    const resolved = dataImport.metadata?.resolved_duplicates ?? [];
    const skipped = dataImport.metadata?.skipped ?? 0;
    const unconverted = dataImport.metadata?.unconverted ?? 0;
    const unassigned = dataImport.metadata?.unassigned ?? 0;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Data imports', href: index() },
            { title: dataImport.name, href: '#' },
        ],
    });

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
                        `uploaded ${date(dataImport.created_at)}${dataImport.user ? ` by ${dataImport.user}` : ''}`,
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
                            description={`${plural(dataImport.transactions_count, 'transaction')} from this file ${dataImport.transactions_count === 1 ? 'is' : 'are'} deleted. Releases and tracks it created stay in the catalog. Statements already generated keep their figures.`}
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

            {review && (
                <DuplicateReview
                    importId={dataImport.id}
                    duplicates={dataImport.metadata?.duplicates ?? []}
                />
            )}

            {resolved.length > 0 && (
                <Panel
                    title="Duplicates decided at import"
                    description="What was done about lines that looked like existing transactions."
                >
                    <div className="grid gap-4">
                        {resolved.map((duplicate) => (
                            <div key={duplicate.key} className="grid gap-2">
                                <p className="text-sm font-medium">
                                    {duplicate.release ?? 'Release'} ·{' '}
                                    {DECISIONS[duplicate.decision] ??
                                        duplicate.decision}
                                </p>
                                <DuplicateSides duplicate={duplicate} />
                            </div>
                        ))}
                    </div>
                </Panel>
            )}

            {!failed && !review && (
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
                            value={count(dataImport.transactions_count)}
                            hint={`of ${count(dataImport.rows_count)} lines in the file`}
                        />
                        <Stat
                            label="Skipped"
                            value={count(dataImport.duplicates_count + skipped)}
                            hint={`${count(dataImport.duplicates_count)} already imported, ${count(skipped)} left out (payouts, lines without an amount, or kinds of sale switched off)`}
                        />
                        <Stat
                            label="Added to catalog"
                            value={`${dataImport.created_releases_count} / ${dataImport.created_tracks_count}`}
                            hint="new releases / tracks"
                        />
                    </div>

                    <MoneyFlow flow={flow} />

                    {(unassigned > 0 ||
                        unconverted > 0 ||
                        dataImport.errors_count > 0) && (
                        <Notice title="To clean up">
                            <ul className="grid gap-1">
                                {unassigned > 0 && (
                                    <li>
                                        {plural(unassigned, 'transaction')}{' '}
                                        could not be matched to a release
                                        {unassignedItems > 0
                                            ? ` (${plural(unassignedItems, 'item')})`
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
                                        {plural(unconverted, 'transaction')}{' '}
                                        {unconverted === 1 ? 'is' : 'are'} in
                                        another currency and{' '}
                                        {unconverted === 1 ? 'has' : 'have'} no
                                        conversion rate, so{' '}
                                        {unconverted === 1
                                            ? 'it is'
                                            : 'they are'}{' '}
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
                                        {plural(
                                            dataImport.errors_count,
                                            'line',
                                        )}{' '}
                                        could not be read and{' '}
                                        {dataImport.errors_count === 1
                                            ? 'was'
                                            : 'were'}{' '}
                                        left out. See below.
                                    </li>
                                )}
                            </ul>
                        </Notice>
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

                    <IncomeReport report={report} />

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
                                        <SortableHead
                                            column="line"
                                            sorter={errorSorter}
                                            className="w-24"
                                        >
                                            Line
                                        </SortableHead>
                                        <SortableHead
                                            column="message"
                                            sorter={errorSorter}
                                        >
                                            Problem
                                        </SortableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {errorRows.map((error, position) => (
                                        <TableRow key={position}>
                                            <TableCell className="tabular-nums">
                                                {error.line ?? '–'}
                                            </TableCell>
                                            <TableCell>
                                                {error.message}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Panel>
                    )}
                </>
            )}
        </Page>
    );
}
