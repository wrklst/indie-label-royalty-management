import { useState } from 'react';
import { Amount } from '@/components/amount';
import { Panel, Stat } from '@/components/page';
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
import { date, percent, quantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { StatementData, StatementLedger } from '@/types';

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
                                    <TableHead>Ledger</TableHead>
                                    <TableHead className="text-right">
                                        Brought forward
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Income
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Expenses
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Share
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Transfers
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Paid out
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Balance
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.ledgers.map((row) => (
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
                                <TableHead>Release</TableHead>
                                <TableHead>Track</TableHead>
                                <TableHead>Format</TableHead>
                                <TableHead className="text-right">
                                    Units
                                </TableHead>
                                <TableHead className="text-right">
                                    Income
                                </TableHead>
                                <TableHead className="text-right">
                                    Split
                                </TableHead>
                                <TableHead className="text-right">
                                    Share
                                </TableHead>
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
                            {data.income_rows.map((row, position) => (
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
                                <TableHead>Release</TableHead>
                                <TableHead>Track</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead className="text-right">
                                    Split
                                </TableHead>
                                <TableHead className="text-right">
                                    Your part
                                </TableHead>
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
                            {data.expense_rows.map((row, position) => (
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
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Ledger</TableHead>
                                <TableHead>Release</TableHead>
                                <TableHead>Memo</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
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
                            {data.transfer_rows.map((row) => (
                                <TableRow key={`transfer-${row.id}`}>
                                    <TableCell>{date(row.date)}</TableCell>
                                    <TableCell>
                                        {row.category ?? 'Transfer'}
                                    </TableCell>
                                    <TableCell>{row.ledger_name}</TableCell>
                                    <TableCell>
                                        {row.release_name ?? '–'}
                                    </TableCell>
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
                            {data.payout_rows.map((row) => (
                                <TableRow key={`payout-${row.id}`}>
                                    <TableCell>{date(row.date)}</TableCell>
                                    <TableCell>Payout</TableCell>
                                    <TableCell>–</TableCell>
                                    <TableCell>–</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {row.memo}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={-Number(row.amount)}
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
