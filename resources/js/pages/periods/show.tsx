import { Link, router } from '@inertiajs/react';
import { Banknote, FileText, Pencil } from 'lucide-react';
import PeriodController from '@/actions/App/Http/Controllers/PeriodController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { Field } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { Page, Panel, Stat } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { date } from '@/lib/format';
import { index as catalog } from '@/routes/catalog';
import { create as createImport } from '@/routes/data-imports';
import { index as items } from '@/routes/items';
import { index, payee as payeeBreakdown } from '@/routes/periods';
import { show as showRelease } from '@/routes/releases';
import { show as showStatement } from '@/routes/statements';
import { index as transactions } from '@/routes/transactions';
import type { PayeeTotals } from '@/types';
import { PeriodFields } from './index';

type PayeeRow = {
    payee_id: number;
    payee_name: string;
    totals: PayeeTotals;
    unrecouped: boolean;
    statement: { id: number; published: boolean; created_at: string } | null;
};

type Props = {
    period: { id: number; name: string; starts_on: string; ends_on: string };
    currency: string;
    totals: Record<
        | 'income'
        | 'expense'
        | 'net'
        | 'payee_share'
        | 'label_share'
        | 'label_costs'
        | 'previous_payable'
        | 'transfers'
        | 'total_payable'
        | 'total_paid'
        | 'outstanding',
        string
    >;
    audit: {
        transactions: number;
        unassigned: number;
        missing_contract: number;
        unconverted: number;
        releases_without_contract: {
            release_id: number;
            release_name: string;
            transactions: number;
            total: string;
        }[];
    };
    payees: PayeeRow[];
};

const today = () => new Date().toISOString().slice(0, 10);
const cents = (value: string) =>
    (Math.floor(Number(value) * 100 + 1e-6) / 100).toFixed(2);

export default function ShowPeriod({
    period,
    currency,
    totals,
    audit,
    payees,
}: Props) {
    const range = { from: period.starts_on, to: period.ends_on, date: 'paid' };
    const issues =
        audit.unassigned + audit.missing_contract + audit.unconverted;
    const withStatement = payees.filter((row) => row.statement).length;

    return (
        <Page
            title={period.name}
            description={`${date(period.starts_on)} – ${date(period.ends_on)} · ${audit.transactions.toLocaleString('en-GB')} transactions paid in this period`}
            actions={
                <>
                    <FormDialog
                        title="Edit period"
                        form={PeriodController.update.form(period.id)}
                        trigger={
                            <Button variant="outline">
                                <Pencil />
                                Edit
                            </Button>
                        }
                    >
                        {(errors) => (
                            <PeriodFields period={period} errors={errors} />
                        )}
                    </FormDialog>
                    <DeleteButton
                        url={PeriodController.destroy.url(period.id)}
                        label="Delete"
                        title={`Delete ${period.name}?`}
                        description="Its statements are deleted with it. Transactions are not touched. A period with payouts cannot be deleted."
                    />
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="Income"
                    value={<Amount value={totals.income} currency={currency} />}
                    hint={
                        <>
                            Expenses{' '}
                            <Amount
                                value={totals.expense}
                                currency={currency}
                            />
                        </>
                    }
                />
                <Stat
                    label="Payee share"
                    value={
                        <Amount
                            value={totals.payee_share}
                            currency={currency}
                        />
                    }
                    hint={
                        <>
                            Label keeps{' '}
                            <Amount
                                value={totals.label_share}
                                currency={currency}
                            />
                            {Number(totals.label_costs) !== 0 && (
                                <>
                                    {' '}
                                    after{' '}
                                    <Amount
                                        value={Math.abs(
                                            Number(totals.label_costs),
                                        )}
                                        currency={currency}
                                    />{' '}
                                    own costs
                                </>
                            )}
                        </>
                    }
                />
                <Stat
                    label="Payable"
                    value={
                        <Amount
                            value={totals.total_payable}
                            currency={currency}
                        />
                    }
                    hint={
                        <>
                            incl.{' '}
                            <Amount
                                value={totals.previous_payable}
                                currency={currency}
                            />{' '}
                            carried over
                        </>
                    }
                />
                <Stat
                    label="Still to pay"
                    value={
                        <Amount
                            value={totals.outstanding}
                            currency={currency}
                        />
                    }
                    hint={
                        <>
                            Paid{' '}
                            <Amount
                                value={totals.total_paid}
                                currency={currency}
                            />
                        </>
                    }
                />
            </div>

            <Panel
                title="1. Check the data"
                description="Anything listed here earns nothing for payees until it is fixed."
            >
                {audit.transactions === 0 ? (
                    <p className="text-sm">
                        No transactions were paid in this period.{' '}
                        <Link
                            href={createImport()}
                            className="underline underline-offset-4"
                        >
                            Import a statement
                        </Link>{' '}
                        and set its paid date inside the period.
                    </p>
                ) : issues === 0 ? (
                    <p className="text-sm text-emerald-700 dark:text-emerald-400">
                        Everything is assigned to a release, covered by a
                        contract and converted.
                    </p>
                ) : (
                    <ul className="grid gap-2 text-sm">
                        {audit.unassigned > 0 && (
                            <li>
                                <Badge
                                    variant="destructive"
                                    className="mr-2 tabular-nums"
                                >
                                    {audit.unassigned.toLocaleString('en-GB')}
                                </Badge>
                                transactions are not assigned to a release.{' '}
                                <Link
                                    href={items({ query: { unassigned: 1 } })}
                                    className="underline underline-offset-4"
                                >
                                    Assign items
                                </Link>
                                {' · '}
                                <Link
                                    href={transactions({
                                        query: { ...range, unassigned: 1 },
                                    })}
                                    className="underline underline-offset-4"
                                >
                                    show transactions
                                </Link>
                            </li>
                        )}
                        {audit.unconverted > 0 && (
                            <li>
                                <Badge
                                    variant="destructive"
                                    className="mr-2 tabular-nums"
                                >
                                    {audit.unconverted.toLocaleString('en-GB')}
                                </Badge>
                                transactions have no conversion rate to{' '}
                                {currency} and are left out.{' '}
                                <Link
                                    href={transactions({
                                        query: { ...range, unconverted: 1 },
                                    })}
                                    className="underline underline-offset-4"
                                >
                                    Show them
                                </Link>
                            </li>
                        )}
                        {audit.missing_contract > 0 && (
                            <li>
                                <Badge
                                    variant="outline"
                                    className="mr-2 tabular-nums"
                                >
                                    {audit.missing_contract.toLocaleString(
                                        'en-GB',
                                    )}
                                </Badge>
                                transactions are on releases without a contract,
                                so the label keeps them in full.{' '}
                                <Link
                                    href={catalog()}
                                    className="underline underline-offset-4"
                                >
                                    Assign contracts
                                </Link>
                                <span className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground">
                                    {audit.releases_without_contract
                                        .slice(0, 12)
                                        .map((release) => (
                                            <span key={release.release_id}>
                                                <Link
                                                    href={showRelease(
                                                        release.release_id,
                                                    )}
                                                    className="underline decoration-muted-foreground/40 underline-offset-4"
                                                >
                                                    {release.release_name}
                                                </Link>{' '}
                                                <Amount
                                                    value={release.total}
                                                    currency={currency}
                                                    muted
                                                />
                                            </span>
                                        ))}
                                    {audit.releases_without_contract.length >
                                        12 && (
                                        <span>
                                            and{' '}
                                            {audit.releases_without_contract
                                                .length - 12}{' '}
                                            more
                                        </span>
                                    )}
                                </span>
                            </li>
                        )}
                    </ul>
                )}
            </Panel>

            <Panel
                title="2. Statements and payouts"
                description={
                    payees.length === 0
                        ? undefined
                        : `${withStatement} of ${payees.length} payees have a statement. Figures below are live; a statement freezes them.`
                }
                flush
                actions={
                    payees.length > 0 && (
                        <Button
                            size="sm"
                            onClick={() =>
                                router.post(
                                    PeriodController.statements.url(period.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <FileText />
                            {withStatement > 0
                                ? 'Regenerate all statements'
                                : 'Generate all statements'}
                        </Button>
                    )
                }
            >
                {payees.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No payee has earnings, transfers or a balance up to this
                        period. Payees appear once their contracts are assigned
                        to releases with income.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Payee</TableHead>
                                <TableHead className="text-right">
                                    Carried over
                                </TableHead>
                                <TableHead className="text-right">
                                    Share
                                </TableHead>
                                <TableHead className="text-right">
                                    Transfers
                                </TableHead>
                                <TableHead className="text-right">
                                    Payable
                                </TableHead>
                                <TableHead className="text-right">
                                    Paid
                                </TableHead>
                                <TableHead className="text-right">
                                    Still to pay
                                </TableHead>
                                <TableHead>Statement</TableHead>
                                <TableHead className="w-28" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payees.map((row) => (
                                <TableRow key={row.payee_id}>
                                    <TableCell>
                                        <Link
                                            href={payeeBreakdown([
                                                period.id,
                                                row.payee_id,
                                            ])}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {row.payee_name}
                                        </Link>
                                        {row.unrecouped && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                unrecouped ledger
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.totals.previous_payable}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.totals.payee_share}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.totals.transfers}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.totals.total_payable}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={row.totals.total_paid}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right font-medium">
                                        <Amount
                                            value={row.totals.outstanding}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {row.statement ? (
                                            <Link
                                                href={showStatement(
                                                    row.statement.id,
                                                )}
                                                className="underline decoration-muted-foreground/40 underline-offset-4"
                                            >
                                                {row.statement.published
                                                    ? 'Published'
                                                    : 'Draft'}
                                            </Link>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                None
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Generate statement for ${row.payee_name}`}
                                                title="Generate statement"
                                                onClick={() =>
                                                    router.post(
                                                        PeriodController.statements.url(
                                                            period.id,
                                                        ),
                                                        {
                                                            payee_id:
                                                                row.payee_id,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <FileText className="text-muted-foreground" />
                                            </Button>
                                            {Number(
                                                cents(row.totals.outstanding),
                                            ) > 0 && (
                                                <FormDialog
                                                    title={`Record payout to ${row.payee_name}`}
                                                    description="Record a payment you have made. It is booked against the payee's ledgers, largest balance first."
                                                    form={PeriodController.payout.form(
                                                        period.id,
                                                    )}
                                                    submitLabel="Record payout"
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Record payout to ${row.payee_name}`}
                                                            title="Record payout"
                                                        >
                                                            <Banknote className="text-muted-foreground" />
                                                        </Button>
                                                    }
                                                >
                                                    {(errors) => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="payee_id"
                                                                value={
                                                                    row.payee_id
                                                                }
                                                            />
                                                            <Field
                                                                label={`Amount (${currency})`}
                                                                htmlFor="total"
                                                                error={
                                                                    errors.total
                                                                }
                                                                required
                                                                hint={`Up to ${cents(row.totals.outstanding)} is payable.`}
                                                            >
                                                                <Input
                                                                    id="total"
                                                                    name="total"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0.01"
                                                                    defaultValue={cents(
                                                                        row
                                                                            .totals
                                                                            .outstanding,
                                                                    )}
                                                                    required
                                                                />
                                                            </Field>
                                                            <Field
                                                                label="Paid on"
                                                                htmlFor="paid_on"
                                                                error={
                                                                    errors.paid_on
                                                                }
                                                                required
                                                            >
                                                                <Input
                                                                    id="paid_on"
                                                                    name="paid_on"
                                                                    type="date"
                                                                    defaultValue={today()}
                                                                    required
                                                                />
                                                            </Field>
                                                            <Field
                                                                label="Memo"
                                                                htmlFor="memo"
                                                                error={
                                                                    errors.memo ??
                                                                    errors.payee_id
                                                                }
                                                            >
                                                                <Input
                                                                    id="memo"
                                                                    name="memo"
                                                                    placeholder="Bank transfer, invoice no.…"
                                                                />
                                                            </Field>
                                                        </>
                                                    )}
                                                </FormDialog>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell>Total</TableCell>
                                <TableCell className="text-right">
                                    <Amount
                                        value={totals.previous_payable}
                                        currency={currency}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Amount
                                        value={totals.payee_share}
                                        currency={currency}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Amount
                                        value={totals.transfers}
                                        currency={currency}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Amount
                                        value={totals.total_payable}
                                        currency={currency}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Amount
                                        value={totals.total_paid}
                                        currency={currency}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Amount
                                        value={totals.outstanding}
                                        currency={currency}
                                    />
                                </TableCell>
                                <TableCell colSpan={2} />
                            </TableRow>
                        </TableFooter>
                    </Table>
                )}
            </Panel>
        </Page>
    );
}

ShowPeriod.layout = {
    breadcrumbs: [
        { title: 'Periods', href: index() },
        { title: 'Period', href: '#' },
    ],
};
