import { Link, setLayoutProps } from '@inertiajs/react';
import { Amount } from '@/components/amount';
import { EmptyState, Page, Panel, Stat } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { SortableHead } from '@/components/sortable-head';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { serverSorter, useTableSort } from '@/hooks/use-table-sort';
import type { Sorter } from '@/hooks/use-table-sort';
import { date } from '@/lib/format';
import { index, show } from '@/routes/portal';
import type { Paginated } from '@/types';

type Statement = {
    id: number;
    name: string;
    balance: string;
    payable: string;
    period: string | null;
    published_at: string | null;
};

type Overview = {
    statement_id: number;
    period: string;
    published_at: string | null;
    currency: string | null;
    owed: string;
    earned: string;
    paid: string;
    paid_since: string;
    ledgers: { name: string; balance: string; unrecouped: boolean }[];
};

type Payout = {
    id: number;
    paid_on: string;
    period: string | null;
    total: string;
    memo: string | null;
};

type Props = {
    view: 'admin' | 'payee' | 'unlinked';
    overview: Overview | null;
    payouts: Paginated<Payout>;
    statements: Paginated<Statement>;
    filters: Record<string, string | null>;
};

const LEDGER_COLUMNS = {
    name: (row: Overview['ledgers'][number]) => row.name,
    balance: (row: Overview['ledgers'][number]) => row.balance,
};

function OverviewSection({ overview }: { overview: Overview }) {
    const { rows: ledgerRows, sorter } = useTableSort(
        overview.ledgers,
        LEDGER_COLUMNS,
    );
    const currency = overview.currency ?? undefined;

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat
                    label="Owed to you"
                    value={<Amount value={overview.owed} currency={currency} />}
                    hint={
                        <>
                            As of {overview.period}
                            {Number(overview.paid_since) > 0 && (
                                <>
                                    . Paid since and not deducted yet:{' '}
                                    <Amount
                                        value={overview.paid_since}
                                        currency={currency}
                                    />
                                </>
                            )}
                        </>
                    }
                />
                <Stat
                    label={`Earned in ${overview.period}`}
                    value={
                        <Amount value={overview.earned} currency={currency} />
                    }
                    hint="Your share after expenses and the contract split."
                />
                <Stat
                    label={`Paid out for ${overview.period}`}
                    value={<Amount value={overview.paid} currency={currency} />}
                    hint={
                        <Link
                            href={show(overview.statement_id)}
                            className="underline underline-offset-4"
                        >
                            Open the statement
                        </Link>
                    }
                />
            </div>

            {overview.ledgers.length > 0 && (
                <Panel
                    flush
                    title="Balance per ledger"
                    description="A ledger below zero is unrecouped: your earnings go towards it first, and it is never set off against your other ledgers."
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Ledger
                                </SortableHead>
                                <SortableHead
                                    column="balance"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Balance
                                </SortableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ledgerRows.map((ledger, index) => (
                                <TableRow key={`${index}-${ledger.name}`}>
                                    <TableCell>
                                        <span className="flex items-center gap-2">
                                            {ledger.name}
                                            {ledger.unrecouped && (
                                                <Badge variant="warning">
                                                    Unrecouped
                                                </Badge>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={ledger.balance}
                                            currency={currency}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>
            )}
        </>
    );
}

function PayoutsSection({
    payouts,
    sorter,
}: {
    payouts: Paginated<Payout>;
    sorter: Sorter;
}) {
    return (
        <Panel
            flush={payouts.total > 0}
            title="Payouts"
            description="Payments the label has made to you."
        >
            {payouts.total === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No payouts have been recorded for you yet.
                </p>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="paid_on"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Paid date
                                </SortableHead>
                                <SortableHead column="period" sorter={sorter}>
                                    For period
                                </SortableHead>
                                <SortableHead column="memo" sorter={sorter}>
                                    Memo
                                </SortableHead>
                                <SortableHead
                                    column="total"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Amount
                                </SortableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payouts.data.map((payout) => (
                                <TableRow key={payout.id}>
                                    <TableCell>
                                        {date(payout.paid_on)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {payout.period ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {payout.memo ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={payout.total} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={payouts} noun="payouts" />
                </>
            )}
        </Panel>
    );
}

function StatementsTable({
    statements,
    sorter,
}: {
    statements: Paginated<Statement>;
    sorter: Sorter;
}) {
    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead column="period" sorter={sorter}>
                            Period
                        </SortableHead>
                        <SortableHead
                            column="published_at"
                            sorter={sorter}
                            first="desc"
                        >
                            Published
                        </SortableHead>
                        <SortableHead
                            column="balance"
                            sorter={sorter}
                            first="desc"
                            className="text-right"
                        >
                            Balance
                        </SortableHead>
                        <SortableHead
                            column="payable"
                            sorter={sorter}
                            first="desc"
                            className="text-right"
                        >
                            Payable
                        </SortableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {statements.data.map((statement) => (
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
            <Pagination page={statements} noun="statements" />
        </>
    );
}

export default function Portal({
    view,
    overview,
    payouts,
    statements,
    filters,
}: Props) {
    const title = 'My statements';
    const { apply } = useFilters(index.url(), filters);
    const statementSorter = serverSorter(filters, apply, 'statements');
    const payoutSorter = serverSorter(filters, apply, 'payouts');

    setLayoutProps({ breadcrumbs: [{ title, href: index() }] });

    if (view === 'admin') {
        return (
            <Page
                title={title}
                description="What payees can see: every published statement of this label. Each payee only gets their own."
            >
                {statements.total === 0 ? (
                    <EmptyState
                        title="No statements yet"
                        description="Statements appear here, and in My statements of the payee, as soon as you publish them."
                    />
                ) : (
                    <Panel flush>
                        <StatementsTable
                            statements={statements}
                            sorter={statementSorter}
                        />
                    </Panel>
                )}
            </Page>
        );
    }

    if (view === 'unlinked') {
        return (
            <Page title={title} description="Your royalties from this label.">
                <EmptyState
                    title="Your access is not linked to a payee yet"
                    description="The label has to connect your account to the payee your royalties are booked on. Ask them to do that in their team settings; your statements and payouts will show up here afterwards."
                />
            </Page>
        );
    }

    return (
        <Page
            title={title}
            description="What you earned, what you are owed and what you have been paid, according to the statements this label has published."
        >
            {overview ? (
                <OverviewSection overview={overview} />
            ) : (
                <EmptyState
                    title="Nothing published yet"
                    description="As soon as the label publishes your first statement, you will see here what you earned in that period, what you are owed and the balance of each of your ledgers. Payouts made to you are listed as well."
                />
            )}

            {(overview || payouts.total > 0) && (
                <PayoutsSection payouts={payouts} sorter={payoutSorter} />
            )}

            {statements.total > 0 && (
                <Panel
                    flush
                    title="Published statements"
                    description="Open a statement for the full breakdown per release and track."
                >
                    <StatementsTable
                        statements={statements}
                        sorter={statementSorter}
                    />
                </Panel>
            )}
        </Page>
    );
}

Portal.layout = { breadcrumbs: [{ title: 'My statements', href: index() }] };
