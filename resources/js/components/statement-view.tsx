import { useMemo, useState } from 'react';
import { Amount } from '@/components/amount';
import { Panel, Stat } from '@/components/page';
import { SortableHead } from '@/components/sortable-head';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableSort } from '@/hooks/use-table-sort';
import { date, percent, quantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    StatementData,
    StatementDetailRow,
    StatementLedger,
} from '@/types';

function LedgerTable({
    ledger,
    currency,
}: {
    ledger: StatementLedger;
    currency: string;
}) {
    return (
        <Panel
            flush
            title={
                <span className="flex items-center gap-2">
                    {ledger.ledger_name}
                    {ledger.unrecouped && (
                        <Badge variant="destructive">Unrecouped</Badge>
                    )}
                </span>
            }
            description="Earnings per release and track, after expenses and the contract split."
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Release / track</TableHead>
                        <TableHead>Contract</TableHead>
                        <TableHead className="text-right">Split</TableHead>
                        <TableHead className="text-right">Income</TableHead>
                        <TableHead className="text-right">Expenses</TableHead>
                        <TableHead className="text-right">Net</TableHead>
                        <TableHead className="text-right">Share</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {ledger.releases.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={7}
                                className="text-muted-foreground"
                            >
                                No income or expenses on this ledger.
                            </TableCell>
                        </TableRow>
                    )}
                    {ledger.releases.map((release) => (
                        <ReleaseRows
                            key={release.release_id ?? 0}
                            release={release}
                            currency={currency}
                        />
                    ))}
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell colSpan={3}>Earned this period</TableCell>
                        <TableCell className="text-right">
                            <Amount value={ledger.income} currency={currency} />
                        </TableCell>
                        <TableCell className="text-right">
                            <Amount
                                value={ledger.expense}
                                currency={currency}
                            />
                        </TableCell>
                        <TableCell className="text-right">
                            <Amount value={ledger.net} currency={currency} />
                        </TableCell>
                        <TableCell className="text-right">
                            <Amount
                                value={ledger.payee_share}
                                currency={currency}
                            />
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
            <dl className="grid gap-x-8 gap-y-1 border-t px-4 py-3 text-sm sm:max-w-md sm:grid-cols-[1fr_auto]">
                <dt className="text-muted-foreground">
                    Balance brought forward
                </dt>
                <dd className="text-right">
                    <Amount
                        value={ledger.previous_balance}
                        currency={currency}
                    />
                </dd>
                <dt className="text-muted-foreground">Share this period</dt>
                <dd className="text-right">
                    <Amount value={ledger.payee_share} currency={currency} />
                </dd>
                <dt className="text-muted-foreground">
                    Transfers (advances, paybacks)
                </dt>
                <dd className="text-right">
                    <Amount value={ledger.transfers} currency={currency} />
                </dd>
                <dt className="text-muted-foreground">Paid out</dt>
                <dd className="text-right">
                    <Amount
                        value={-Number(ledger.payouts)}
                        currency={currency}
                    />
                </dd>
                <dt className="font-medium">
                    {ledger.unrecouped
                        ? 'Unrecouped, carried forward'
                        : 'Balance'}
                </dt>
                <dd className="text-right font-medium">
                    <Amount value={ledger.balance} currency={currency} />
                </dd>
            </dl>
        </Panel>
    );
}

function ReleaseRows({
    release,
    currency,
}: {
    release: StatementLedger['releases'][number];
    currency: string;
}) {
    return (
        <>
            <TableRow className="bg-muted/40">
                <TableCell colSpan={3} className="font-medium">
                    {release.release_name}
                </TableCell>
                <TableCell className="text-right">
                    <Amount value={release.income} currency={currency} />
                </TableCell>
                <TableCell className="text-right">
                    <Amount value={release.expense} currency={currency} />
                </TableCell>
                <TableCell className="text-right">
                    <Amount value={release.net} currency={currency} />
                </TableCell>
                <TableCell className="text-right font-medium">
                    <Amount value={release.payee_share} currency={currency} />
                </TableCell>
            </TableRow>
            {release.tracks.map((track, position) => (
                <TableRow
                    key={`${track.track_id}-${track.contract_id}-${position}`}
                >
                    <TableCell className="pl-8">
                        {track.track_id === null
                            ? 'Whole release'
                            : track.track_name}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                        {track.contract_name ?? '–'}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                        {percent(track.split)}
                    </TableCell>
                    <TableCell className="text-right">
                        <Amount
                            value={track.income}
                            currency={currency}
                            precise
                        />
                    </TableCell>
                    <TableCell className="text-right">
                        <Amount
                            value={track.expense}
                            currency={currency}
                            precise
                        />
                    </TableCell>
                    <TableCell className="text-right">
                        <Amount value={track.net} currency={currency} precise />
                    </TableCell>
                    <TableCell className="text-right">
                        <Amount
                            value={track.payee_share}
                            currency={currency}
                            precise
                        />
                    </TableCell>
                </TableRow>
            ))}
        </>
    );
}

type Movement = {
    key: string;
    date: string;
    type: string;
    ledger: string | null;
    release: string | null;
    memo: string | null;
    amount: string | number;
};

const LEDGER_COLUMNS = {
    ledger_name: (row: StatementLedger) => row.ledger_name,
    previous_balance: (row: StatementLedger) => row.previous_balance,
    income: (row: StatementLedger) => row.income,
    expense: (row: StatementLedger) => row.expense,
    payee_share: (row: StatementLedger) => row.payee_share,
    transfers: (row: StatementLedger) => row.transfers,
    payouts: (row: StatementLedger) => row.payouts,
    balance: (row: StatementLedger) => row.balance,
};

const DETAIL_COLUMNS = {
    release: (row: StatementDetailRow) => row.release_name,
    track: (row: StatementDetailRow) => row.track_name ?? 'Whole release',
    format: (row: StatementDetailRow) => row.format ?? row.category,
    category: (row: StatementDetailRow) => row.category,
    quantity: (row: StatementDetailRow) => row.quantity,
    amount: (row: StatementDetailRow) => row.amount,
    split: (row: StatementDetailRow) => row.split,
    payee_share: (row: StatementDetailRow) => row.payee_share,
};

const MOVEMENT_COLUMNS = {
    date: (row: Movement) => row.date,
    type: (row: Movement) => row.type,
    ledger: (row: Movement) => row.ledger,
    release: (row: Movement) => row.release,
    memo: (row: Movement) => row.memo,
    amount: (row: Movement) => row.amount,
};

/**
 * A payee's figures for one period: summary, one tab per ledger, and the
 * income, expense and transfer detail behind them.
 */
export function StatementView({ data }: { data: StatementData }) {
    const tabs = [
        { key: 'summary', label: 'Summary' },
        ...data.ledgers.map((ledger) => ({
            key: `ledger-${ledger.ledger_id ?? 0}`,
            label: `${ledger.ledger_name} ledger`,
        })),
        { key: 'income', label: `Income (${data.income_rows.length})` },
        { key: 'expenses', label: `Expenses (${data.expense_rows.length})` },
        {
            key: 'transfers',
            label: `Transfers & payouts (${data.transfer_rows.length + data.payout_rows.length})`,
        },
    ];
    const [tab, setTab] = useState('summary');
    const { rows: ledgerRows, sorter: ledgerSorter } = useTableSort(
        data.ledgers,
        LEDGER_COLUMNS,
    );
    const { rows: incomeRows, sorter: incomeSorter } = useTableSort(
        data.income_rows,
        DETAIL_COLUMNS,
    );
    const { rows: expenseRows, sorter: expenseSorter } = useTableSort(
        data.expense_rows,
        DETAIL_COLUMNS,
    );
    // Transfers and payouts share one table, payouts shown as money out.
    const movements = useMemo<Movement[]>(
        () => [
            ...data.transfer_rows.map((row) => ({
                key: `transfer-${row.id}`,
                date: row.date,
                type: row.category ?? 'Transfer',
                ledger: row.ledger_name,
                release: row.release_name,
                memo: row.memo,
                amount: row.amount,
            })),
            ...data.payout_rows.map((row) => ({
                key: `payout-${row.id}`,
                date: row.date,
                type: 'Payout',
                ledger: null,
                release: null,
                memo: row.memo,
                amount: -Number(row.amount),
            })),
        ],
        [data.transfer_rows, data.payout_rows],
    );
    const { rows: movementRows, sorter: movementSorter } = useTableSort(
        movements,
        MOVEMENT_COLUMNS,
    );
    const currency = data.currency;
    const totals = data.totals;
    const ledger = data.ledgers.find(
        (candidate) => `ledger-${candidate.ledger_id ?? 0}` === tab,
    );

    return (
        <div className="grid gap-6">
            <div role="tablist" className="flex flex-wrap gap-1 border-b">
                {tabs.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === item.key}
                        onClick={() => setTab(item.key)}
                        className={cn(
                            '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                            tab === item.key
                                ? 'border-foreground text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            {tab === 'summary' && (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat
                            label="Brought forward"
                            value={
                                <Amount
                                    value={totals.previous_balance}
                                    currency={currency}
                                />
                            }
                            hint="Balance before this period"
                        />
                        <Stat
                            label="Share this period"
                            value={
                                <Amount
                                    value={totals.payee_share}
                                    currency={currency}
                                />
                            }
                            hint={
                                <>
                                    Net{' '}
                                    <Amount
                                        value={totals.net}
                                        currency={currency}
                                    />{' '}
                                    before the split
                                </>
                            }
                        />
                        <Stat
                            label="Paid out"
                            value={
                                <Amount
                                    value={totals.total_paid}
                                    currency={currency}
                                />
                            }
                            hint={
                                <>
                                    of{' '}
                                    <Amount
                                        value={totals.total_payable}
                                        currency={currency}
                                    />{' '}
                                    payable
                                </>
                            }
                        />
                        <Stat
                            label="Still payable"
                            value={
                                <Amount
                                    value={totals.outstanding}
                                    currency={currency}
                                />
                            }
                            hint={
                                Number(totals.balance) <
                                Number(totals.outstanding)
                                    ? 'Unrecouped ledgers are not deducted from this'
                                    : undefined
                            }
                        />
                    </div>
                    <Panel title="Ledgers" flush>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <SortableHead
                                        column="ledger_name"
                                        sorter={ledgerSorter}
                                    >
                                        Ledger
                                    </SortableHead>
                                    <SortableHead
                                        column="previous_balance"
                                        sorter={ledgerSorter}
                                        first="desc"
                                        className="text-right"
                                    >
                                        Brought forward
                                    </SortableHead>
                                    <SortableHead
                                        column="income"
                                        sorter={ledgerSorter}
                                        first="desc"
                                        className="text-right"
                                    >
                                        Income
                                    </SortableHead>
                                    <SortableHead
                                        column="expense"
                                        sorter={ledgerSorter}
                                        first="desc"
                                        className="text-right"
                                    >
                                        Expenses
                                    </SortableHead>
                                    <SortableHead
                                        column="payee_share"
                                        sorter={ledgerSorter}
                                        first="desc"
                                        className="text-right"
                                    >
                                        Share
                                    </SortableHead>
                                    <SortableHead
                                        column="transfers"
                                        sorter={ledgerSorter}
                                        first="desc"
                                        className="text-right"
                                    >
                                        Transfers
                                    </SortableHead>
                                    <SortableHead
                                        column="payouts"
                                        sorter={ledgerSorter}
                                        first="desc"
                                        className="text-right"
                                    >
                                        Paid out
                                    </SortableHead>
                                    <SortableHead
                                        column="balance"
                                        sorter={ledgerSorter}
                                        first="desc"
                                        className="text-right"
                                    >
                                        Balance
                                    </SortableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {ledgerRows.map((row) => (
                                    <TableRow
                                        key={row.ledger_id ?? 0}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            setTab(
                                                `ledger-${row.ledger_id ?? 0}`,
                                            )
                                        }
                                    >
                                        <TableCell className="font-medium">
                                            {row.ledger_name}
                                            {row.unrecouped && (
                                                <Badge
                                                    variant="destructive"
                                                    className="ml-2"
                                                >
                                                    Unrecouped
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={row.previous_balance}
                                                currency={currency}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={row.income}
                                                currency={currency}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={row.expense}
                                                currency={currency}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={row.payee_share}
                                                currency={currency}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={row.transfers}
                                                currency={currency}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={row.payouts}
                                                currency={currency}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            <Amount
                                                value={row.balance}
                                                currency={currency}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Panel>
                </>
            )}

            {ledger && <LedgerTable ledger={ledger} currency={currency} />}

            {tab === 'income' && (
                <Panel
                    flush
                    title="Income"
                    description="Grouped by release, track and format."
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="release"
                                    sorter={incomeSorter}
                                >
                                    Release
                                </SortableHead>
                                <SortableHead
                                    column="track"
                                    sorter={incomeSorter}
                                >
                                    Track
                                </SortableHead>
                                <SortableHead
                                    column="format"
                                    sorter={incomeSorter}
                                >
                                    Format
                                </SortableHead>
                                <SortableHead
                                    column="quantity"
                                    sorter={incomeSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Units
                                </SortableHead>
                                <SortableHead
                                    column="amount"
                                    sorter={incomeSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Income
                                </SortableHead>
                                <SortableHead
                                    column="split"
                                    sorter={incomeSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Split
                                </SortableHead>
                                <SortableHead
                                    column="payee_share"
                                    sorter={incomeSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Share
                                </SortableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.income_rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-muted-foreground"
                                    >
                                        No income this period.
                                    </TableCell>
                                </TableRow>
                            )}
                            {incomeRows.map((row, position) => (
                                <TableRow key={position}>
                                    <TableCell>{row.release_name}</TableCell>
                                    <TableCell>
                                        {row.track_name ?? 'Whole release'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {row.format ?? row.category ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {quantity(row.quantity)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.amount}
                                            currency={currency}
                                            precise
                                        />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {percent(row.split)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.payee_share}
                                            currency={currency}
                                            precise
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>
            )}

            {tab === 'expenses' && (
                <Panel
                    flush
                    title="Expenses"
                    description="Costs charged against the same releases and tracks before the split."
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="release"
                                    sorter={expenseSorter}
                                >
                                    Release
                                </SortableHead>
                                <SortableHead
                                    column="track"
                                    sorter={expenseSorter}
                                >
                                    Track
                                </SortableHead>
                                <SortableHead
                                    column="category"
                                    sorter={expenseSorter}
                                >
                                    Category
                                </SortableHead>
                                <SortableHead
                                    column="amount"
                                    sorter={expenseSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Amount
                                </SortableHead>
                                <SortableHead
                                    column="split"
                                    sorter={expenseSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Split
                                </SortableHead>
                                <SortableHead
                                    column="payee_share"
                                    sorter={expenseSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Your part
                                </SortableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.expense_rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground"
                                    >
                                        No expenses this period.
                                    </TableCell>
                                </TableRow>
                            )}
                            {expenseRows.map((row, position) => (
                                <TableRow key={position}>
                                    <TableCell>{row.release_name}</TableCell>
                                    <TableCell>
                                        {row.track_name ?? 'Whole release'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {row.category ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.amount}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {percent(row.split)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.payee_share}
                                            currency={currency}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>
            )}

            {tab === 'transfers' && (
                <Panel
                    flush
                    title="Transfers and payouts"
                    description="Negative amounts were paid or given to the payee."
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="date"
                                    sorter={movementSorter}
                                    first="desc"
                                >
                                    Date
                                </SortableHead>
                                <SortableHead
                                    column="type"
                                    sorter={movementSorter}
                                >
                                    Type
                                </SortableHead>
                                <SortableHead
                                    column="ledger"
                                    sorter={movementSorter}
                                >
                                    Ledger
                                </SortableHead>
                                <SortableHead
                                    column="release"
                                    sorter={movementSorter}
                                >
                                    Release
                                </SortableHead>
                                <SortableHead
                                    column="memo"
                                    sorter={movementSorter}
                                >
                                    Memo
                                </SortableHead>
                                <SortableHead
                                    column="amount"
                                    sorter={movementSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Amount
                                </SortableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.transfer_rows.length +
                                data.payout_rows.length ===
                                0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground"
                                    >
                                        No transfers or payouts this period.
                                    </TableCell>
                                </TableRow>
                            )}
                            {movementRows.map((row) => (
                                <TableRow key={row.key}>
                                    <TableCell>{date(row.date)}</TableCell>
                                    <TableCell>{row.type}</TableCell>
                                    <TableCell>{row.ledger ?? '–'}</TableCell>
                                    <TableCell>{row.release ?? '–'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {row.memo}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.amount}
                                            currency={currency}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>
            )}
        </div>
    );
}
