import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import PeriodController from '@/actions/App/Http/Controllers/PeriodController';
import { Amount } from '@/components/amount';
import { Field } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { EmptyState, Page, Panel } from '@/components/page';
import { SortableHead } from '@/components/sortable-head';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableSort } from '@/hooks/use-table-sort';
import { count, date } from '@/lib/format';
import { index, show } from '@/routes/periods';

type Period = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    payouts_count: number;
    statements_count: number;
    paid: string;
};

export function PeriodFields({
    period,
    errors,
}: {
    period: { name: string; starts_on: string; ends_on: string };
    errors: Record<string, string>;
}) {
    return (
        <>
            <Field label="Name" htmlFor="name" error={errors.name} required>
                <Input
                    id="name"
                    name="name"
                    defaultValue={period.name}
                    required
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="From"
                    htmlFor="starts_on"
                    error={errors.starts_on}
                    required
                >
                    <Input
                        id="starts_on"
                        name="starts_on"
                        type="date"
                        defaultValue={period.starts_on}
                        required
                    />
                </Field>
                <Field
                    label="To"
                    htmlFor="ends_on"
                    error={errors.ends_on}
                    required
                >
                    <Input
                        id="ends_on"
                        name="ends_on"
                        type="date"
                        defaultValue={period.ends_on}
                        required
                    />
                </Field>
            </div>
            <p className="text-xs text-muted-foreground">
                Transactions count in the period their paid date falls in.
                Periods may not overlap.
            </p>
        </>
    );
}

const SORT_COLUMNS = {
    name: (row: Period) => row.name,
    starts_on: (row: Period) => row.starts_on,
    ends_on: (row: Period) => row.ends_on,
    statements_count: (row: Period) => row.statements_count,
    payouts_count: (row: Period) => row.payouts_count,
    paid: (row: Period) => row.paid,
};

export default function Periods({
    periods,
    suggestion,
}: {
    periods: Period[];
    suggestion: { name: string; starts_on: string; ends_on: string };
}) {
    const { rows, sorter } = useTableSort(periods, SORT_COLUMNS);
    const newPeriod = (
        <FormDialog
            title="New period"
            form={PeriodController.store.form()}
            submitLabel="Create period"
            trigger={
                <Button>
                    <Plus />
                    New period
                </Button>
            }
        >
            {(errors) => <PeriodFields period={suggestion} errors={errors} />}
        </FormDialog>
    );

    return (
        <Page
            title="Periods"
            description="An accounting period is the time span you settle with your payees, usually a quarter or half year. Open a period to check the figures, generate statements and record payouts."
            actions={newPeriod}
        >
            {periods.length === 0 ? (
                <EmptyState
                    title="No periods yet"
                    description={`Start with ${suggestion.name}. You can create earlier periods afterwards; balances always carry forward by date.`}
                    action={newPeriod}
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Period
                                </SortableHead>
                                <SortableHead
                                    column="starts_on"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    From
                                </SortableHead>
                                <SortableHead
                                    column="ends_on"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    To
                                </SortableHead>
                                <SortableHead
                                    column="statements_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Statements
                                </SortableHead>
                                <SortableHead
                                    column="payouts_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Payouts
                                </SortableHead>
                                <SortableHead
                                    column="paid"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Paid out
                                </SortableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((period) => (
                                <TableRow key={period.id}>
                                    <TableCell>
                                        <Link
                                            href={show(period.id)}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {period.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {date(period.starts_on)}
                                    </TableCell>
                                    <TableCell>
                                        {date(period.ends_on)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count(period.statements_count)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count(period.payouts_count)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={period.paid} />
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

Periods.layout = { breadcrumbs: [{ title: 'Periods', href: index() }] };
