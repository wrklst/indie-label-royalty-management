import { Link } from '@inertiajs/react';
import { Pencil, Plus, Repeat } from 'lucide-react';
import { useState } from 'react';
import LabelCostController from '@/actions/App/Http/Controllers/LabelCostController';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { Field, SelectInput } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { EmptyState, Page, Panel, Stat } from '@/components/page';
import { SortableHead } from '@/components/sortable-head';
import { Badge } from '@/components/ui/badge';
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
import { useTableSort } from '@/hooks/use-table-sort';
import { date } from '@/lib/format';
import { index } from '@/routes/label-costs';

type Recurring = {
    id: number;
    name: string;
    category: string | null;
    amount: string;
    currency: string;
    conversion_rate: string | null;
    memo: string | null;
    interval: string;
    starts_on: string;
    ends_on: string | null;
    next_due_on: string | null;
    occurrences_booked: number;
};

type Cost = {
    id: number;
    category: string | null;
    memo: string | null;
    source: string | null;
    total: string;
    converted_total: string | null;
    currency: string;
    paid_on: string;
    recurring: boolean;
};

type Props = {
    currency: string;
    categories: string[];
    intervals: { value: string; label: string }[];
    totals: { this_year: string; last_year: string; year: number };
    recurring: Recurring[];
    costs: Cost[];
};

// The local date: toISOString() is UTC and gives yesterday shortly after midnight.
const today = () => new Date().toLocaleDateString('sv-SE');
const plain = (value: string) => value.replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1');
const PER: Record<string, string> = {
    monthly: 'per month',
    quarterly: 'per quarter',
    yearly: 'per year',
};

function AmountFields({
    labelCurrency,
    categories,
    errors,
    cost,
    amountLabel,
}: {
    labelCurrency: string;
    categories: string[];
    errors: Record<string, string>;
    cost?: Recurring;
    amountLabel: string;
}) {
    const [currency, setCurrency] = useState(cost?.currency ?? labelCurrency);

    return (
        <>
            <Field
                label="What is it for"
                htmlFor="name"
                error={errors.name}
                required
            >
                <Input
                    id="name"
                    name="name"
                    defaultValue={cost?.name}
                    placeholder="Bandcamp Pro"
                    required
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-[1fr_6rem]">
                <Field
                    label={amountLabel}
                    htmlFor="amount"
                    error={errors.amount}
                    required
                    hint="Enter the cost as a positive number."
                >
                    <Input
                        id="amount"
                        name="amount"
                        type="number"
                        step="any"
                        min="0"
                        defaultValue={cost ? plain(cost.amount) : ''}
                        required
                    />
                </Field>
                <Field
                    label="Currency"
                    htmlFor="currency"
                    error={errors.currency}
                    required
                >
                    <Input
                        id="currency"
                        name="currency"
                        value={currency}
                        onChange={(event) =>
                            setCurrency(event.target.value.toUpperCase())
                        }
                        maxLength={3}
                        className="uppercase"
                        required
                    />
                </Field>
            </div>
            {currency !== labelCurrency && (
                <Field
                    label={`Rate to ${labelCurrency}`}
                    htmlFor="conversion_rate"
                    error={errors.conversion_rate}
                    hint={`1 ${currency} = ? ${labelCurrency}. Without a rate the cost is not counted.`}
                >
                    <Input
                        id="conversion_rate"
                        name="conversion_rate"
                        type="number"
                        step="any"
                        min="0"
                        defaultValue={cost?.conversion_rate ?? ''}
                    />
                </Field>
            )}
            <Field label="Category" htmlFor="category" error={errors.category}>
                <SelectInput
                    id="category"
                    name="category"
                    defaultValue={cost?.category ?? ''}
                    placeholder="No category"
                    options={[
                        ...new Set([
                            ...categories,
                            ...(cost?.category ? [cost.category] : []),
                        ]),
                    ]}
                />
            </Field>
            <Field label="Note" htmlFor="memo" error={errors.memo}>
                <Input id="memo" name="memo" defaultValue={cost?.memo ?? ''} />
            </Field>
        </>
    );
}

const RECURRING_COLUMNS = {
    name: (row: Recurring) => row.name,
    category: (row: Recurring) => row.category,
    amount: (row: Recurring) => row.amount,
    starts_on: (row: Recurring) => row.starts_on,
    next_due_on: (row: Recurring) => row.next_due_on,
    occurrences_booked: (row: Recurring) => row.occurrences_booked,
};

const COST_COLUMNS = {
    paid_on: (row: Cost) => row.paid_on,
    name: (row: Cost) => row.source ?? row.memo,
    category: (row: Cost) => row.category,
    amount: (row: Cost) => row.converted_total,
};

export default function LabelCosts({
    currency,
    categories,
    intervals,
    totals,
    recurring,
    costs,
}: Props) {
    const { rows: recurringRows, sorter: recurringSorter } = useTableSort(
        recurring,
        RECURRING_COLUMNS,
    );
    const { rows: costRows, sorter: costSorter } = useTableSort(
        costs,
        COST_COLUMNS,
    );
    const yearly = recurring
        .filter(
            (cost) => cost.next_due_on !== null && cost.currency === currency,
        )
        .reduce(
            (sum, cost) =>
                sum +
                Number(cost.amount) *
                    (cost.interval === 'monthly'
                        ? 12
                        : cost.interval === 'quarterly'
                          ? 4
                          : 1),
            0,
        );

    const oneTime = (
        <FormDialog
            title="One-time cost"
            description="A cost of running the label that belongs to no release. The label bears it alone; royalties are not affected."
            form={LabelCostController.store.form()}
            submitLabel="Book cost"
            trigger={
                <Button variant="outline">
                    <Plus />
                    One-time cost
                </Button>
            }
        >
            {(errors) => (
                <>
                    <AmountFields
                        labelCurrency={currency}
                        categories={categories}
                        errors={errors}
                        amountLabel="Amount"
                    />
                    <Field
                        label="Paid on"
                        htmlFor="paid_on"
                        error={errors.paid_on}
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
                </>
            )}
        </FormDialog>
    );

    const repeating = (
        <FormDialog
            title="Recurring cost"
            description="Booked automatically on every due date. Due dates in the past are booked right away."
            form={LabelCostController.storeRecurring.form()}
            submitLabel="Add recurring cost"
            trigger={
                <Button>
                    <Repeat />
                    Recurring cost
                </Button>
            }
        >
            {(errors) => (
                <>
                    <AmountFields
                        labelCurrency={currency}
                        categories={categories}
                        errors={errors}
                        amountLabel="Amount each time"
                    />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Repeats"
                            htmlFor="interval"
                            error={errors.interval}
                            required
                        >
                            <SelectInput
                                id="interval"
                                name="interval"
                                defaultValue="monthly"
                            >
                                {intervals.map((interval) => (
                                    <option
                                        key={interval.value}
                                        value={interval.value}
                                    >
                                        {interval.label}
                                    </option>
                                ))}
                            </SelectInput>
                        </Field>
                        <Field
                            label="First payment"
                            htmlFor="starts_on"
                            error={errors.starts_on}
                            required
                        >
                            <Input
                                id="starts_on"
                                name="starts_on"
                                type="date"
                                defaultValue={today()}
                                required
                            />
                        </Field>
                        <Field
                            label="Last payment"
                            htmlFor="ends_on"
                            error={errors.ends_on}
                            hint="Empty: until stopped."
                        >
                            <Input id="ends_on" name="ends_on" type="date" />
                        </Field>
                    </div>
                </>
            )}
        </FormDialog>
    );

    return (
        <Page
            title="Label costs"
            description="What it costs to run the label itself: subscriptions, software, accounting, fees. These are borne by the label alone, show up as “Label overheads” in the profitability report and never reduce what payees earn. Costs of a single release are booked as expenses on that release instead."
            actions={
                <>
                    {oneTime}
                    {repeating}
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat
                    label={`Costs in ${totals.year}`}
                    value={
                        <Amount value={totals.this_year} currency={currency} />
                    }
                />
                <Stat
                    label={`Costs in ${totals.year - 1}`}
                    value={
                        <Amount value={totals.last_year} currency={currency} />
                    }
                />
                <Stat
                    label="Recurring, per year"
                    value={<Amount value={-yearly} currency={currency} />}
                    hint="From the recurring costs still running"
                />
            </div>

            <Panel title="Recurring costs" flush>
                {recurring.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        Nothing recurring yet. Add a subscription once and it is
                        booked on every due date.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="name"
                                    sorter={recurringSorter}
                                >
                                    Cost
                                </SortableHead>
                                <SortableHead
                                    column="category"
                                    sorter={recurringSorter}
                                >
                                    Category
                                </SortableHead>
                                <SortableHead
                                    column="amount"
                                    sorter={recurringSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Amount
                                </SortableHead>
                                <SortableHead
                                    column="starts_on"
                                    sorter={recurringSorter}
                                    first="desc"
                                >
                                    Since
                                </SortableHead>
                                <SortableHead
                                    column="next_due_on"
                                    sorter={recurringSorter}
                                >
                                    Next payment
                                </SortableHead>
                                <SortableHead
                                    column="occurrences_booked"
                                    sorter={recurringSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Booked
                                </SortableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {recurringRows.map((cost) => (
                                <TableRow key={cost.id}>
                                    <TableCell className="font-medium">
                                        {cost.name}
                                        {cost.memo && (
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                {cost.memo}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {cost.category ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        <Amount
                                            value={cost.amount}
                                            currency={cost.currency}
                                        />{' '}
                                        <span className="text-xs text-muted-foreground">
                                            {PER[cost.interval]}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {date(cost.starts_on)}
                                    </TableCell>
                                    <TableCell>
                                        {cost.next_due_on ? (
                                            date(cost.next_due_on)
                                        ) : (
                                            <Badge variant="outline">
                                                Ended {date(cost.ends_on)}
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {cost.occurrences_booked}×
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <FormDialog
                                                title={`Change ${cost.name}`}
                                                description="Applies to payments from now on. Payments already booked keep their amounts."
                                                form={LabelCostController.updateRecurring.form(
                                                    cost.id,
                                                )}
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Change ${cost.name}`}
                                                    >
                                                        <Pencil className="text-muted-foreground" />
                                                    </Button>
                                                }
                                            >
                                                {(errors) => (
                                                    <>
                                                        <AmountFields
                                                            labelCurrency={
                                                                currency
                                                            }
                                                            categories={
                                                                categories
                                                            }
                                                            errors={errors}
                                                            cost={cost}
                                                            amountLabel="Amount each time"
                                                        />
                                                        <Field
                                                            label="Last payment"
                                                            htmlFor="ends_on"
                                                            error={
                                                                errors.ends_on
                                                            }
                                                            hint="Set a date to end the cost; empty keeps it running."
                                                        >
                                                            <Input
                                                                id="ends_on"
                                                                name="ends_on"
                                                                type="date"
                                                                defaultValue={
                                                                    cost.ends_on ??
                                                                    ''
                                                                }
                                                            />
                                                        </Field>
                                                    </>
                                                )}
                                            </FormDialog>
                                            <DeleteButton
                                                url={LabelCostController.destroyRecurring.url(
                                                    cost.id,
                                                )}
                                                confirmLabel="Stop"
                                                title={`Stop ${cost.name}?`}
                                                description={`No further payments are booked. The ${cost.occurrences_booked} already booked stay in the accounts.`}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>

            {costs.length === 0 ? (
                <EmptyState
                    title="No label costs booked yet"
                    description="Add a one-time cost, or a recurring one such as a subscription."
                />
            ) : (
                <Panel
                    title="Booked costs"
                    description="The latest 200. Each is an ordinary expense and can be changed or deleted."
                    flush
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="paid_on"
                                    sorter={costSorter}
                                    first="desc"
                                >
                                    Paid
                                </SortableHead>
                                <SortableHead column="name" sorter={costSorter}>
                                    Cost
                                </SortableHead>
                                <SortableHead
                                    column="category"
                                    sorter={costSorter}
                                >
                                    Category
                                </SortableHead>
                                <SortableHead
                                    column="amount"
                                    sorter={costSorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Amount
                                </SortableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {costRows.map((cost) => (
                                <TableRow key={cost.id}>
                                    <TableCell className="whitespace-nowrap">
                                        {date(cost.paid_on)}
                                    </TableCell>
                                    <TableCell>
                                        {cost.source ?? cost.memo ?? '–'}
                                        {cost.recurring && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                recurring
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {cost.category ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {cost.converted_total === null ? (
                                            <Badge variant="destructive">
                                                {Number(cost.total).toFixed(2)}{' '}
                                                {cost.currency} · no rate
                                            </Badge>
                                        ) : (
                                            <Amount
                                                value={cost.converted_total}
                                                currency={currency}
                                            />
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={TransactionController.edit.url(
                                                        cost.id,
                                                    )}
                                                    aria-label="Edit cost"
                                                >
                                                    <Pencil className="text-muted-foreground" />
                                                </Link>
                                            </Button>
                                            <DeleteButton
                                                url={TransactionController.destroy.url(
                                                    cost.id,
                                                )}
                                                title="Delete this cost?"
                                                description="It is removed from the label result. A recurring cost will not book this date again."
                                            />
                                        </div>
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

LabelCosts.layout = { breadcrumbs: [{ title: 'Label costs', href: index() }] };
