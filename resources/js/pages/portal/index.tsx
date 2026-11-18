import { Link } from '@inertiajs/react';
import { Amount } from '@/components/amount';
import { EmptyState, Page, Panel } from '@/components/page';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { date } from '@/lib/format';
import { index, show } from '@/routes/portal';

type Statement = {
    id: number;
    name: string;
    balance: string;
    payable: string;
    period: string | null;
    published_at: string | null;
};

export default function Portal({ statements }: { statements: Statement[] }) {
    return (
        <Page
            title="My statements"
            description="Your royalty statements from this label."
        >
            {statements.length === 0 ? (
                <EmptyState
                    title="No statements yet"
                    description="You will find your statements here as soon as the label publishes them."
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead>Published</TableHead>
                                <TableHead className="text-right">
                                    Balance
                                </TableHead>
                                <TableHead className="text-right">
                                    Payable
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {statements.map((statement) => (
                                <TableRow key={statement.id}>
                                    <TableCell>
                                        <Link
                                            href={show(statement.id)}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {statement.period ?? statement.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {date(statement.published_at)}
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
                </Panel>
            )}
        </Page>
    );
}

Portal.layout = { breadcrumbs: [{ title: 'My statements', href: index() }] };
