import { Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import DataImportController from '@/actions/App/Http/Controllers/DataImportController';
import { FilterBar } from '@/components/filter-bar';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { SortableHead } from '@/components/sortable-head';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { serverSorter } from '@/hooks/use-table-sort';
import type { SortDirection } from '@/hooks/use-table-sort';
import { count, date } from '@/lib/format';
import { create, index, show } from '@/routes/data-imports';
import type { Paginated } from '@/types';

type DataImport = {
    id: number;
    name: string;
    importer_name: string;
    original_filename: string | null;
    status: 'pending' | 'imported' | 'review' | 'failed' | 'deleted';
    rows_count: number;
    transactions_count: number;
    duplicates_count: number;
    errors_count: number;
    needs_cleanup: boolean;
    total: string;
    default_paid_timestamp: string | null;
    created_at: string;
    user: string | null;
};

export function ImportStatusBadge({
    status,
    needsCleanup,
}: {
    status: DataImport['status'];
    needsCleanup: boolean;
}) {
    if (status === 'failed') {
        return <Badge variant="destructive">Failed</Badge>;
    }

    if (status === 'deleted') {
        return <Badge variant="outline">Deleted</Badge>;
    }

    if (status === 'review') {
        return <Badge variant="warning">Duplicates to review</Badge>;
    }

    if (status === 'pending') {
        return <Badge variant="info">Pending</Badge>;
    }

    return needsCleanup ? (
        <Badge variant="warning">Needs cleanup</Badge>
    ) : (
        <Badge variant="success">Imported</Badge>
    );
}

export default function DataImports({
    imports,
    filters,
    sourceOptions,
}: {
    imports: Paginated<DataImport>;
    filters: {
        search: string;
        source: string;
        status: string | null;
        sort: string | null;
        direction: SortDirection | null;
    };
    sourceOptions: { id: string; name: string }[];
}) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);
    const filtered =
        filters.search !== '' ||
        filters.source !== '' ||
        filters.status !== null;

    return (
        <Page
            title="Data imports"
            description="Each uploaded statement is one import. Deleting an import removes exactly the transactions it created."
            actions={
                <Button asChild>
                    <Link href={create()}>
                        <Upload />
                        Import a statement
                    </Link>
                </Button>
            }
        >
            <FilterBar
                url={index.url()}
                filters={filters}
                search="Search name or file name…"
                selects={[
                    {
                        name: 'source',
                        label: 'Source',
                        placeholder: 'All sources',
                        options: sourceOptions,
                    },
                    {
                        name: 'status',
                        label: 'Status',
                        placeholder: 'Any status',
                        className: 'w-full sm:w-48',
                        children: (
                            <>
                                <option value="imported">Imported</option>
                                <option value="review">
                                    Duplicates to review
                                </option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                                <option value="deleted">Deleted</option>
                            </>
                        ),
                    },
                ]}
            />

            {imports.total === 0 ? (
                <EmptyState
                    title={filtered ? 'No import matches' : 'No imports yet'}
                    description={
                        filtered
                            ? undefined
                            : 'Upload a statement from your distributor, Bandcamp or a neighbouring rights society, or a spreadsheet in the template layout.'
                    }
                    action={
                        filtered ? undefined : (
                            <Button asChild>
                                <Link href={create()}>Import a statement</Link>
                            </Button>
                        )
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    File
                                </SortableHead>
                                <SortableHead column="importer" sorter={sorter}>
                                    Source
                                </SortableHead>
                                <SortableHead column="status" sorter={sorter}>
                                    Status
                                </SortableHead>
                                <SortableHead
                                    column="default_paid_timestamp"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Paid date
                                </SortableHead>
                                <SortableHead
                                    column="rows_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Rows
                                </SortableHead>
                                <SortableHead
                                    column="transactions_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Transactions
                                </SortableHead>
                                <SortableHead
                                    column="duplicates_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Skipped duplicates
                                </SortableHead>
                                <SortableHead
                                    column="total"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Total
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
                            {imports.data.map((dataImport) => (
                                <TableRow key={dataImport.id}>
                                    <TableCell className="max-w-sm">
                                        <Link
                                            href={show(dataImport.id)}
                                            className="font-medium wrap-anywhere underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {dataImport.name}
                                        </Link>
                                        {dataImport.original_filename && (
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {dataImport.original_filename}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {dataImport.importer_name}
                                    </TableCell>
                                    <TableCell>
                                        <ImportStatusBadge
                                            status={dataImport.status}
                                            needsCleanup={
                                                dataImport.needs_cleanup
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {dataImport.default_paid_timestamp
                                            ? date(
                                                  dataImport.default_paid_timestamp,
                                              )
                                            : 'From file'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count(dataImport.rows_count)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count(dataImport.transactions_count)}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {count(dataImport.duplicates_count)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={dataImport.total} />
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {date(dataImport.created_at)}
                                        {dataImport.user && (
                                            <span className="block text-xs">
                                                {dataImport.user}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <DeleteButton
                                            url={DataImportController.destroy.url(
                                                dataImport.id,
                                            )}
                                            title={`Delete ${dataImport.name}?`}
                                            description={`The transactions it created (${count(dataImport.transactions_count)}) are removed. Periods that included them change accordingly.`}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={imports} noun="imports" />
                </Panel>
            )}
        </Page>
    );
}

DataImports.layout = {
    breadcrumbs: [{ title: 'Data imports', href: index() }],
};
