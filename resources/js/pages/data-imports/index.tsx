import { Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { Amount } from '@/components/amount';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { SortableHead } from '@/components/sortable-head';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { serverSorter } from '@/hooks/use-table-sort';
import type { SortDirection } from '@/hooks/use-table-sort';
import { date } from '@/lib/format';
import { create, index, show } from '@/routes/data-imports';
import type { Paginated } from '@/types';

type DataImport = {
    id: number;
    name: string;
    importer_name: string;
    original_filename: string | null;
    status: 'pending' | 'imported' | 'review' | 'failed' | 'deleted';
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
}: {
    imports: Paginated<DataImport>;
    filters: { sort: string | null; direction: SortDirection | null };
}) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);

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
            {imports.total === 0 ? (
                <EmptyState
                    title="No imports yet"
                    description="Upload a statement from your distributor, Bandcamp or a neighbouring rights society, or a spreadsheet in the template layout."
                    action={
                        <Button asChild>
                            <Link href={create()}>Import a statement</Link>
                        </Button>
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Import
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
                                        {dataImport.transactions_count.toLocaleString(
                                            'en-GB',
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {dataImport.duplicates_count.toLocaleString(
                                            'en-GB',
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={dataImport.total} />
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {date(
                                            dataImport.created_at.slice(0, 10),
                                        )}
                                        {dataImport.user && (
                                            <span className="block text-xs">
                                                {dataImport.user}
                                            </span>
                                        )}
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
