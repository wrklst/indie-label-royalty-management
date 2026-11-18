import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import PayeeController from '@/actions/App/Http/Controllers/PayeeController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { FormDialog } from '@/components/form-dialog';
import { Page, Panel, Stat } from '@/components/page';
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
import { date, percent } from '@/lib/format';
import { edit as editContract } from '@/routes/contracts';
import { index } from '@/routes/payees';
import { payee as periodPayee } from '@/routes/periods';
import { show as showStatement } from '@/routes/statements';
import { create as createTransaction } from '@/routes/transactions';
import type { PayeeTotals, StatementLedger } from '@/types';
import { PayeeFields } from './index';
import type { Payee } from './index';

type Props = {
    payee: Payee;
    period: { id: number; name: string } | null;
    summary: {
        totals: PayeeTotals;
        ledgers: Omit<StatementLedger, 'releases'>[];
    } | null;
    splits: {
        id: number;
        contract_id: number;
        contract: string | null;
        split_amount: string;
        ledger: string | null;
        territory: string | null;
        used_by: string[];
    }[];
    payouts: {
        id: number;
        total: string;
        paid_on: string;
        period: string | null;
        memo: string | null;
    }[];
    statements: {
        id: number;
        name: string;
        period: string | null;
        payable: string;
        published: boolean;
    }[];
    transfers: {
        id: number;
        paid_timestamp: string;
        total: string;
        converted_total: string | null;
        currency: string;
        category: string | null;
        memo: string | null;
        release: string | null;
        ledger: string | null;
    }[];
};

export default function ShowPayee({
    payee,
    period,
    summary,
    splits,
    payouts,
    statements,
    transfers,
}: Props) {
    return (
        <Page
            title={payee.name}
            description={payee.notes ?? payee.email ?? undefined}
            actions={
                <>
                    <Button variant="outline" asChild>
                        <Link
                            href={createTransaction({
                                query: { type: 'transfer', payee: payee.id },
                            })}
                        >
                            <Plus />
                            Advance or transfer
                        </Link>
                    </Button>
                    <FormDialog
                        title={`Edit ${payee.name}`}
                        form={PayeeController.update.form(payee.id)}
                        trigger={
                            <Button variant="outline">
                                <Pencil />
                                Edit
                            </Button>
                        }
                    >
                        {(errors) => (
                            <PayeeFields payee={payee} errors={errors} />
                        )}
                    </FormDialog>
                    <DeleteButton
                        url={PayeeController.destroy.url(payee.id)}
                        label="Delete"
                        title={`Delete ${payee.name}?`}
                        description="The payee is removed from every contract. Payees with transfers, payouts or statements cannot be deleted."
                    />
                </>
            }
        >
            {summary && period ? (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat
                            label={`Earned in ${period.name}`}
                            value={
                                <Amount value={summary.totals.payee_share} />
                            }
                        />
                        <Stat
                            label="Payable now"
                            value={
                                <Amount value={summary.totals.outstanding} />
                            }
                            hint="Sum of ledgers with a positive balance"
                        />
                        <Stat
                            label={`Paid for ${period.name}`}
                            value={<Amount value={summary.totals.total_paid} />}
                        />
                        <Stat
                            label="Balance across ledgers"
                            value={<Amount value={summary.totals.balance} />}
                        />
                    </div>
                    <Panel
                        title="Ledger balances"
                        description={`As of the end of ${period.name}`}
                        flush
                        actions={
                            <Button variant="outline" size="sm" asChild>
                                <Link href={periodPayee([period.id, payee.id])}>
                                    Full breakdown
                                </Link>
                            </Button>
                        }
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Ledger</TableHead>
                                    <TableHead className="text-right">
                                        Previous balance
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Share
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Transfers
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Payouts
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Balance
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {summary.ledgers.map((ledger) => (
                                    <TableRow key={ledger.ledger_id ?? 0}>
                                        <TableCell className="font-medium">
                                            {ledger.ledger_name}
                                            {ledger.unrecouped && (
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
                                                value={ledger.previous_balance}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={ledger.payee_share}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount value={ledger.transfers} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount value={ledger.payouts} />
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            <Amount value={ledger.balance} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Panel>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {period
                        ? `No earnings, transfers or balance up to ${period.name}.`
                        : 'Create a period to see what this payee has earned.'}
                </p>
            )}

            <Panel
                title="Splits"
                description="The contracts this payee earns from"
                flush
            >
                {splits.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        Not on any contract yet. Open a contract and add a split
                        for this payee.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Contract</TableHead>
                                <TableHead className="text-right">
                                    Split
                                </TableHead>
                                <TableHead>Ledger</TableHead>
                                <TableHead>Territory</TableHead>
                                <TableHead>Applies to</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {splits.map((split) => (
                                <TableRow key={split.id}>
                                    <TableCell>
                                        <Link
                                            href={editContract(
                                                split.contract_id,
                                            )}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {split.contract}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {percent(split.split_amount)}
                                    </TableCell>
                                    <TableCell>
                                        {split.ledger ?? 'Primary'}
                                    </TableCell>
                                    <TableCell>
                                        {split.territory ?? 'World'}
                                    </TableCell>
                                    <TableCell className="max-w-md truncate text-muted-foreground">
                                        {split.used_by.join(', ') ||
                                            'Not assigned to a release or track'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>

            <div className="grid gap-6 xl:grid-cols-2">
                <Panel title="Statements" flush>
                    {statements.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No statements generated yet.
                        </p>
                    ) : (
                        <Table>
                            <TableBody>
                                {statements.map((statement) => (
                                    <TableRow key={statement.id}>
                                        <TableCell>
                                            <Link
                                                href={showStatement(
                                                    statement.id,
                                                )}
                                                className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                            >
                                                {statement.period}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {statement.published ? (
                                                <Badge variant="secondary">
                                                    Published
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Draft
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount value={statement.payable} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>
                <Panel title="Payouts" flush>
                    {payouts.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            Nothing paid out yet.
                        </p>
                    ) : (
                        <Table>
                            <TableBody>
                                {payouts.map((payout) => (
                                    <TableRow key={payout.id}>
                                        <TableCell>
                                            {date(payout.paid_on)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {payout.period}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {payout.memo}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount value={payout.total} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>
            </div>

            <Panel
                title="Transfers"
                description="Advances, paybacks and opening balances. Negative amounts were given to the payee and reduce what they are owed."
                flush
            >
                {transfers.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No transfers.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Paid</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Release</TableHead>
                                <TableHead>Ledger</TableHead>
                                <TableHead>Memo</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transfers.map((transfer) => (
                                <TableRow key={transfer.id}>
                                    <TableCell>
                                        {date(transfer.paid_timestamp)}
                                    </TableCell>
                                    <TableCell>
                                        {transfer.category ?? '–'}
                                    </TableCell>
                                    <TableCell>
                                        {transfer.release ?? '–'}
                                    </TableCell>
                                    <TableCell>
                                        {transfer.ledger ?? 'From contract'}
                                    </TableCell>
                                    <TableCell className="max-w-sm truncate text-muted-foreground">
                                        {transfer.memo}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {transfer.converted_total === null ? (
                                            <Badge variant="destructive">
                                                {transfer.total}{' '}
                                                {transfer.currency} unconverted
                                            </Badge>
                                        ) : (
                                            <Amount
                                                value={transfer.converted_total}
                                            />
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>
        </Page>
    );
}

ShowPayee.layout = {
    breadcrumbs: [
        { title: 'Payees', href: index() },
        { title: 'Payee', href: '#' },
    ],
};
