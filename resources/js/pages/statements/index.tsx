import { Link } from '@inertiajs/react';
import { Amount } from '@/components/amount';
import { CheckField } from '@/components/field';
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
import { useFilters } from '@/hooks/use-filters';
import { date } from '@/lib/format';
import { index as periods } from '@/routes/periods';
import { index, show } from '@/routes/statements';
import type { Paginated } from '@/types';

type Statement = {
    id: number;
    name: string;
    archived: boolean;
    balance: string;
    payable: string;
    revision_of_id: number | null;
    payee: string | null;
    period: string | null;
    published: boolean;
    created_at: string;
};

export default function Statements({
    statements,
    filters,
}: {
    statements: Paginated<Statement>;
    filters: { archived: boolean; period: number | null };
}) {
    const { apply } = useFilters(index.url(), filters);

    return (
        <Page
            title="Statements"
            description="A statement is a frozen copy of a payee's figures for a period. Later changes to transactions or contracts do not alter it; regenerate it to get a new revision."
        >
            <CheckField
                label="Include archived revisions"
                checked={filters.archived}
                onChange={(event) => apply({ archived: event.target.checked })}
            />

            {statements.total === 0 ? (
                <EmptyState
                    title="No statements yet"
                    description="Statements are generated from a period."
                    action={
                        <Button asChild>
                            <Link href={periods()}>Go to periods</Link>
                        </Button>
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Payee</TableHead>
                                <TableHead>Period</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Generated</TableHead>
                                <TableHead className="text-right">
                                    Balance
                                </TableHead>
                                <TableHead className="text-right">
                                    Payable
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {statements.data.map((statement) => (
                                <TableRow
                                    key={statement.id}
                                    className={
                                        statement.archived
                                            ? 'text-muted-foreground'
                                            : ''
                                    }
                                >
                                    <TableCell>
                                        <Link
                                            href={show(statement.id)}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {statement.payee ?? statement.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{statement.period}</TableCell>
                                    <TableCell>
                                        <span className="flex gap-1">
                                            {statement.archived ? (
                                                <Badge variant="outline">
                                                    Archived
                                                </Badge>
                                            ) : statement.published ? (
                                                <Badge variant="secondary">
                                                    Published
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Draft
                                                </Badge>
                                            )}
                                            {statement.revision_of_id &&
                                                !statement.archived && (
                                                    <Badge variant="outline">
                                                        Revised
                                                    </Badge>
                                                )}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {date(
                                            statement.created_at.slice(0, 10),
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={statement.balance} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={statement.payable} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={statements} noun="statements" />
                </Panel>
            )}
        </Page>
    );
}

Statements.layout = { breadcrumbs: [{ title: 'Statements', href: index() }] };
