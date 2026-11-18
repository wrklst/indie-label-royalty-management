import { Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { Amount } from '@/components/amount';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
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
import { date } from '@/lib/format';
import { create, index, show } from '@/routes/data-imports';
import type { Paginated } from '@/types';

type DataImport = {
    id: number;
    name: string;
    importer_name: string;
    original_filename: string | null;
    status: 'pending' | 'imported' | 'failed' | 'deleted';
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

    if (status === 'pending') {
        return <Badge variant="outline">Pending</Badge>;
    }

    return needsCleanup ? (
        <Badge variant="destructive">Needs cleanup</Badge>
    ) : (
        <Badge variant="secondary">Imported</Badge>
    );
}

export default function DataImports({
    imports,
}: {
    imports: Paginated<DataImport>;
}) {
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
                    description="Upload a Zebralution, Bandcamp or neighbouring rights statement, or a spreadsheet in the template layout."
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
                                <TableHead>Import</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Paid date</TableHead>
                                <TableHead className="text-right">
                                    Transactions
                                </TableHead>
                                <TableHead className="text-right">
                                    Skipped duplicates
                                </TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead>Uploaded</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {imports.data.map((dataImport) => (
                                <TableRow key={dataImport.id}>
                                    <TableCell className="max-w-sm">
                                        <Link
                                            href={show(dataImport.id)}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
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
