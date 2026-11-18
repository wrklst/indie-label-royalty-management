import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import PeriodController from '@/actions/App/Http/Controllers/PeriodController';
import { Amount } from '@/components/amount';
import { Field } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { EmptyState, Page, Panel } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { date } from '@/lib/format';
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

export default function Periods({
    periods,
    suggestion,
}: {
    periods: Period[];
    suggestion: { name: string; starts_on: string; ends_on: string };
}) {
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
                                <TableHead>Period</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead className="text-right">
                                    Statements
                                </TableHead>
                                <TableHead className="text-right">
                                    Payouts
                                </TableHead>
                                <TableHead className="text-right">
                                    Paid out
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {periods.map((period) => (
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
                                        {period.statements_count}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {period.payouts_count}
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
