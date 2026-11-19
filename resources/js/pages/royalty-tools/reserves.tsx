import { Form, Link } from '@inertiajs/react';
import ReservesController from '@/actions/App/Http/Controllers/RoyaltyTools/ReservesController';
import { Amount } from '@/components/amount';
import { Field } from '@/components/field';
import { Page, Panel, Stat } from '@/components/page';
import { RecordLink } from '@/components/record-link';
import {
    CreateTransactions,
    Notice,
    OverlapNotice,
    RunsPanel,
    plain,
    today,
} from '@/components/royalty-tool';
import type { ToolRun } from '@/components/royalty-tool';
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
import { index as items } from '@/routes/items';
import { edit as editRelease } from '@/routes/releases';
import { index } from '@/routes/royalty-tools/reserves';

type Row = {
    release_id: number;
    release_name: string;
    base: string;
    rate: string;
    amount: string;
    held: boolean;
};

type Props = {
    currency: string;
    runs: ToolRun[];
    overlaps: ToolRun[];
    filters: { from: string | null; to: string | null };
    report: {
        rows: Row[];
        total: string;
        base: string;
        unconverted: number;
    } | null;
    balances: {
        release_id: number;
        release_name: string;
        held: string;
        liquidated: string;
        balance: string;
    }[];
};

export default function Reserves({
    currency,
    runs,
    overlaps,
    filters,
    report,
    balances,
}: Props) {
    const held = report?.rows.filter((row) => row.held).length ?? 0;

    return (
        <Page
            title="Reserves"
            description="Hold a percentage of income for custom periods of time to account for returns etc. Pick the paid dates, check the report, then create the transactions."
        >
            <Panel
                title="How it is booked"
                description="The transactions are ordinary ones and flow through contracts, statements and ledgers like any other."
            >
                <div className="grid max-w-3xl gap-2 text-sm text-muted-foreground">
                    <p>
                        The reserve of a release is its physical income paid in
                        the range × the “Reserves %” set on the release. Returns
                        in the range lower it.
                    </p>
                    <p>
                        Creating the transactions books two lines per release on
                        its “[Reserves]” item: the reserve as a negative amount
                        on the day it is held, and the same amount back on the
                        day it is liquidated. The release earns less in the
                        period of the first date and gets it back in the period
                        of the second, for payees and label alike. Nothing is
                        liquidated automatically; you choose both dates.
                    </p>
                    <p>
                        The item follows the release contract. Change that on
                        the{' '}
                        <Link
                            href={items({ query: { search: '[Reserves]' } })}
                            className="underline"
                        >
                            Items page
                        </Link>{' '}
                        if a reserve should hit other payees.
                    </p>
                </div>
            </Panel>

            <Panel title="Report">
                <Form
                    action={index.url()}
                    method="get"
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label="Paid from"
                                    htmlFor="from"
                                    error={errors.from}
                                    required
                                >
                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                        required
                                    />
                                </Field>
                                <Field
                                    label="Paid until"
                                    htmlFor="to"
                                    error={errors.to}
                                    required
                                >
                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                        required
                                    />
                                </Field>
                            </div>
                            <div>
                                <Button disabled={processing}>
                                    Show report
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </Panel>

            {report && (
                <>
                    <OverlapNotice overlaps={overlaps} />

                    {report.unconverted > 0 && (
                        <Notice>
                            {report.unconverted} physical income{' '}
                            {report.unconverted === 1
                                ? 'transaction has'
                                : 'transactions have'}{' '}
                            no converted amount and{' '}
                            {report.unconverted === 1 ? 'is' : 'are'} not
                            counted.
                        </Notice>
                    )}

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Stat
                            label="Reserves to hold"
                            value={
                                <Amount
                                    value={report.total}
                                    currency={currency}
                                />
                            }
                        />
                        <Stat
                            label="Physical income"
                            value={
                                <Amount
                                    value={report.base}
                                    currency={currency}
                                />
                            }
                        />
                        <Stat label="Releases" value={held} />
                    </div>

                    <Panel
                        title="Calculated reserves"
                        actions={
                            held > 0 && (
                                <CreateTransactions
                                    form={ReservesController.store.form()}
                                    fields={filters}
                                    overlaps={overlaps}
                                    description={`Books a held and a liquidated line for each of the ${held} releases, as one data import.`}
                                >
                                    {(errors) => (
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field
                                                label="Held on"
                                                htmlFor="held_on"
                                                error={errors.held_on}
                                                hint="The reserve is taken out in the period of this date."
                                                required
                                            >
                                                <Input
                                                    id="held_on"
                                                    name="held_on"
                                                    type="date"
                                                    defaultValue={
                                                        filters.to ?? today()
                                                    }
                                                    required
                                                />
                                            </Field>
                                            <Field
                                                label="Liquidated on"
                                                htmlFor="liquidated_on"
                                                error={errors.liquidated_on}
                                                hint="It is paid back in the period of this date."
                                                required
                                            >
                                                <Input
                                                    id="liquidated_on"
                                                    name="liquidated_on"
                                                    type="date"
                                                    required
                                                />
                                            </Field>
                                        </div>
                                    )}
                                </CreateTransactions>
                            )
                        }
                        flush
                    >
                        {report.rows.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">
                                No physical income was paid in this range on a
                                release that has a reserves rate.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Release</TableHead>
                                        <TableHead className="text-right">
                                            Physical income
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Rate
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Reserve
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.rows.map((row) => (
                                        <TableRow key={row.release_id}>
                                            <TableCell className="font-medium">
                                                <Link
                                                    href={editRelease(
                                                        row.release_id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {row.release_name}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Amount
                                                    value={row.base}
                                                    currency={currency}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {plain(row.rate)}%
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {row.held ? (
                                                    <Amount
                                                        value={row.amount}
                                                        currency={currency}
                                                    />
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        nothing to hold
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                                <TableFooter>
                                    <TableRow>
                                        <TableCell>Total held</TableCell>
                                        <TableCell className="text-right">
                                            <Amount
                                                value={report.base}
                                                currency={currency}
                                            />
                                        </TableCell>
                                        <TableCell />
                                        <TableCell className="text-right">
                                            <Amount
                                                value={report.total}
                                                currency={currency}
                                            />
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        )}
                    </Panel>
                </>
            )}

            <Panel
                title="Reserves by release"
                description="Everything held so far, what has been liquidated up to today, and what is still held."
                flush
            >
                {balances.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No reserves have been booked yet.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Release</TableHead>
                                <TableHead className="text-right">
                                    Held
                                </TableHead>
                                <TableHead className="text-right">
                                    Liquidated so far
                                </TableHead>
                                <TableHead className="text-right">
                                    Still held
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {balances.map((balance) => (
                                <TableRow key={balance.release_id}>
                                    <TableCell className="font-medium">
                                        <RecordLink
                                            to="release"
                                            id={balance.release_id}
                                        >
                                            {balance.release_name}
                                        </RecordLink>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={balance.held}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={balance.liquidated}
                                            currency={currency}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right font-medium">
                                        <Amount
                                            value={balance.balance}
                                            currency={currency}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>

            <RunsPanel
                runs={runs}
                currency={currency}
                totalLabel="Reserves held"
            />
        </Page>
    );
}

Reserves.layout = { breadcrumbs: [{ title: 'Reserves', href: index() }] };
