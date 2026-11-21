import { Form, router } from '@inertiajs/react';
import { Paperclip, Pencil, Plus, Repeat, ScanText } from 'lucide-react';
import { useEffect, useState } from 'react';
import LabelCostController from '@/actions/App/Http/Controllers/LabelCostController';
import ReceiptController from '@/actions/App/Http/Controllers/ReceiptController';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { CheckField, Field, SelectInput } from '@/components/field';
import { FilterBar } from '@/components/filter-bar';
import { FileDrop } from '@/components/file-drop';
import { FormDialog } from '@/components/form-dialog';
import { EmptyState, Page, Panel, Stat } from '@/components/page';
import { Pagination } from '@/components/pagination';
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
import { useFilters } from '@/hooks/use-filters';
import { serverSorter } from '@/hooks/use-table-sort';
import { date, money, plural, today } from '@/lib/format';
import { index } from '@/routes/label-costs';
import type { Paginated } from '@/types';

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
    /** The positive amount a cost is typed and shown as. */
    amount: string;
    /** Stored positive: a refund or credit that reduces the costs. */
    refund: boolean;
    conversion_rate: string | null;
    paid_timestamp: string;
    recurring: boolean;
    receipt_id: number | null;
};

type Receipt = {
    id: number;
    original_name: string;
    status: 'new' | 'reading' | 'read' | 'failed';
    vendor: string | null;
    amount: string | null;
    currency: string | null;
    category: string | null;
    summary: string | null;
    error: string | null;
    paid_on: string | null;
    uploaded_on: string | null;
    suggested_cost_id: number | null;
};

type AttachableCost = Pick<
    Cost,
    'id' | 'source' | 'memo' | 'total' | 'currency' | 'paid_timestamp'
>;

type Props = {
    currency: string;
    categories: string[];
    intervals: { value: string; label: string }[];
    totals: {
        this_year: string;
        last_year: string;
        year: number;
        recurring_per_year: string;
        recurring_without_rate: number;
    };
    filters: Record<string, string | null> & { search: string };
    recurring: Paginated<Recurring>;
    costs: Paginated<Cost>;
    attachableCosts: AttachableCost[];
    receipts: Paginated<Receipt>;
    receiptReading: boolean;
    receiptsBeingRead: boolean;
};

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
    refund,
}: {
    labelCurrency: string;
    categories: string[];
    errors: Record<string, string>;
    cost?: Pick<
        Recurring,
        'name' | 'amount' | 'currency' | 'category' | 'memo' | 'conversion_rate'
    >;
    amountLabel: string;
    /** Offer "Refund or credit" (one-off costs), ticked or not. Recurring costs have none. */
    refund?: boolean;
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
            {refund !== undefined && (
                <CheckField
                    name="refund"
                    withZero
                    label="Refund or credit"
                    hint="Tick it when the amount comes back to the label: it reduces the costs instead of adding to them."
                    defaultChecked={refund}
                />
            )}
            {currency !== labelCurrency && (
                <Field
                    label={`Rate to ${labelCurrency}`}
                    htmlFor="conversion_rate"
                    error={errors.conversion_rate}
                    hint={`1 ${currency} = ? ${labelCurrency}. Leave empty to use the European Central Bank's rate of the day of each payment.`}
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
            <Field label="Memo" htmlFor="memo" error={errors.memo}>
                <Input id="memo" name="memo" defaultValue={cost?.memo ?? ''} />
            </Field>
        </>
    );
}

const READ_CHOICE_KEY = 'receipt-upload-read';

/**
 * Receipts are uploaded as they are dropped. With an AI service connected
 * each upload is a choice: read by the AI, or entered by hand. The last
 * choice is remembered in this browser; the first is "Enter by hand", so
 * no document goes to a third party unless it was chosen.
 */
function ReceiptUpload({ receiptReading }: { receiptReading: boolean }) {
    const [read, setRead] = useState(() => {
        try {
            return window.localStorage.getItem(READ_CHOICE_KEY) === '1';
        } catch {
            return false;
        }
    });
    const choose = (value: boolean) => {
        setRead(value);

        try {
            window.localStorage.setItem(READ_CHOICE_KEY, value ? '1' : '0');
        } catch {
            // The choice is then not remembered.
        }
    };

    return (
        <div data-receipt-upload className="grid gap-2">
            {receiptReading && (
                <fieldset className="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
                    <legend className="sr-only">
                        What happens to an upload
                    </legend>
                    <label className="flex items-center gap-2">
                        <input
                            type="radio"
                            name="receipt-upload-read"
                            checked={!read}
                            onChange={() => choose(false)}
                        />
                        Enter by hand
                    </label>
                    <label className="flex items-center gap-2">
                        <input
                            type="radio"
                            name="receipt-upload-read"
                            checked={read}
                            onChange={() => choose(true)}
                        />
                        Read with AI
                    </label>
                </fieldset>
            )}
            <FileDrop
                data={{ read: receiptReading && read ? '1' : '0' }}
                url={ReceiptController.store.url()}
                accept={[
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ]}
                maxMegabytes={20}
                label="Drop receipts here"
            />
            <p className="text-xs text-muted-foreground">
                PDF, JPEG, PNG or WebP, up to 20 MB each.
                {receiptReading
                    ? ' With Read with AI the file is sent to your AI service.'
                    : ' Reading receipts with AI can be switched on under Settings → Connections.'}
            </p>
        </div>
    );
}

/**
 * One receipt that belongs to no cost yet: what was read off it, and the
 * ways to book it.
 */
function ReceiptRow({
    receipt,
    costs,
    currency,
    categories,
    receiptReading,
}: {
    receipt: Receipt;
    costs: AttachableCost[];
    currency: string;
    categories: string[];
    receiptReading: boolean;
}) {
    const name =
        receipt.vendor ?? receipt.original_name.replace(/\.[^.]+$/, '');

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-2 text-sm">
            <div className="min-w-0 flex-1">
                <a
                    href={ReceiptController.show.url(receipt.id)}
                    target="_blank"
                    rel="noreferrer"
                    className="font-medium wrap-anywhere underline decoration-muted-foreground/40 underline-offset-4"
                >
                    {receipt.original_name}
                </a>
                <div className="text-xs text-muted-foreground">
                    {receipt.status === 'reading' &&
                        'Being read by the AI… You can book it by hand meanwhile.'}
                    {receipt.status === 'failed' &&
                        `Could not be read: ${receipt.error ?? 'unknown reason'}`}
                    {receipt.status === 'new' &&
                        `Uploaded ${receipt.uploaded_on ? date(receipt.uploaded_on) : ''}, not read`}
                    {receipt.status === 'read' &&
                        [
                            receipt.vendor,
                            receipt.paid_on && date(receipt.paid_on),
                            receipt.amount &&
                                money(
                                    receipt.amount,
                                    receipt.currency ?? currency,
                                ),
                            receipt.category,
                            receipt.summary,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <FormDialog
                    title="Book receipt as a cost"
                    description={
                        receipt.status === 'read'
                            ? 'What the AI read off the receipt is a proposal: check it and change what is wrong. The receipt is attached to the cost.'
                            : 'Enter the cost. The receipt is attached to it.'
                    }
                    form={LabelCostController.store.form()}
                    submitLabel="Book cost"
                    trigger={
                        <Button variant="outline" size="sm">
                            <Plus />
                            Book as cost
                        </Button>
                    }
                >
                    {(errors) => (
                        <>
                            <input
                                type="hidden"
                                name="receipt_id"
                                value={receipt.id}
                            />
                            <AmountFields
                                labelCurrency={currency}
                                categories={categories}
                                errors={errors}
                                amountLabel="Amount"
                                refund={false}
                                cost={{
                                    name,
                                    amount: receipt.amount ?? '',
                                    currency: receipt.currency ?? currency,
                                    category: receipt.category,
                                    memo: receipt.summary,
                                    conversion_rate: null,
                                }}
                            />
                            <Field
                                label="Paid date"
                                htmlFor="paid_timestamp"
                                error={errors.paid_timestamp}
                                required
                            >
                                <Input
                                    id="paid_timestamp"
                                    name="paid_timestamp"
                                    type="date"
                                    defaultValue={receipt.paid_on ?? today()}
                                    required
                                />
                            </Field>
                        </>
                    )}
                </FormDialog>
                {costs.length > 0 && (
                    <Form
                        {...ReceiptController.update.form(receipt.id)}
                        options={{ preserveScroll: true }}
                        className="flex items-center gap-2"
                    >
                        {({ processing }) => (
                            <>
                                <SelectInput
                                    name="transaction_id"
                                    aria-label={`Cost that ${receipt.original_name} belongs to`}
                                    defaultValue={
                                        receipt.suggested_cost_id ?? ''
                                    }
                                    className="max-w-56"
                                    required
                                >
                                    <option value="">Attach to a cost…</option>
                                    {costs.map((cost) => (
                                        <option key={cost.id} value={cost.id}>
                                            {date(cost.paid_timestamp)} ·{' '}
                                            {cost.source ?? cost.memo ?? 'Cost'}{' '}
                                            ·{' '}
                                            {money(
                                                Math.abs(Number(cost.total)),
                                                cost.currency,
                                            )}
                                            {cost.id ===
                                            receipt.suggested_cost_id
                                                ? ' (matches)'
                                                : ''}
                                        </option>
                                    ))}
                                </SelectInput>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Attach
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                {receiptReading && receipt.status !== 'reading' && (
                    <Button
                        variant="outline"
                        size="sm"
                        aria-label={`${receipt.status === 'read' ? 'Read again' : 'Read with AI'}: ${receipt.original_name}`}
                        onClick={() =>
                            router.post(
                                ReceiptController.read.url(receipt.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <ScanText />
                        {receipt.status === 'read'
                            ? 'Read again'
                            : 'Read with AI'}
                    </Button>
                )}
                <DeleteButton
                    url={ReceiptController.destroy.url(receipt.id)}
                    title={`Delete ${receipt.original_name}?`}
                    description="The file is removed. Costs are not touched."
                />
            </div>
        </li>
    );
}

export default function LabelCosts({
    currency,
    categories,
    intervals,
    totals,
    filters,
    recurring,
    costs,
    attachableCosts,
    receipts,
    receiptReading,
    receiptsBeingRead: reading,
}: Props) {
    // What was read off a receipt arrives while the page is open.

    useEffect(() => {
        if (!reading) {
            return;
        }

        const timer = window.setInterval(
            () =>
                router.reload({
                    only: [
                        'receipts',
                        'receiptsBeingRead',
                        'costs',
                        'attachableCosts',
                    ],
                }),
            4000,
        );

        return () => window.clearInterval(timer);
    }, [reading]);

    const { apply } = useFilters(index.url(), filters);
    const recurringSorter = serverSorter(filters, apply, 'recurring');
    const costSorter = serverSorter(filters, apply, 'costs');

    const oneOff = (
        <FormDialog
            title="One-off cost"
            description="A cost of running the label that belongs to no release. The label bears it alone; royalties are not affected."
            form={LabelCostController.store.form()}
            submitLabel="Book cost"
            trigger={
                <Button variant="outline">
                    <Plus />
                    One-off cost
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
                        refund={false}
                    />
                    <Field
                        label="Paid date"
                        htmlFor="paid_timestamp"
                        error={errors.paid_timestamp}
                        required
                    >
                        <Input
                            id="paid_timestamp"
                            name="paid_timestamp"
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
            submitLabel="Create recurring cost"
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
                            hint="A date in the past books every payment since then at once."
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
            title="Overheads"
            description="What it costs to run the label itself: subscriptions, software, accounting, fees. These are label costs that belong to no release: the label bears them alone, they show up as “Overheads” in the profitability report and they never reduce what payees earn. Costs of a single release are booked as expenses on that release instead."
            actions={
                <>
                    {oneOff}
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
                    value={
                        <Amount
                            value={`-${totals.recurring_per_year}`}
                            currency={currency}
                        />
                    }
                    hint={
                        totals.recurring_without_rate > 0
                            ? `From the recurring costs still running, without ${plural(totals.recurring_without_rate, 'cost')} whose exchange rate is not available`
                            : 'From the recurring costs still running, each in the label currency'
                    }
                />
            </div>

            <Panel title="Recurring costs" flush>
                {recurring.total === 0 ? (
                    <EmptyState
                        compact
                        title="Nothing recurring yet."
                        description="Add a subscription once and it is booked on every due date."
                    />
                ) : (
                    <>
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
                                {recurring.data.map((cost) => (
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
                                                    confirmLabel={
                                                        cost.occurrences_booked >
                                                        0
                                                            ? 'Stop, keep the payments'
                                                            : 'Delete'
                                                    }
                                                    alternative={
                                                        cost.occurrences_booked >
                                                        0
                                                            ? {
                                                                  label: `Delete with its ${plural(cost.occurrences_booked, 'payment', 'payments')}`,
                                                                  url: LabelCostController.destroyRecurring.url(
                                                                      cost.id,
                                                                      {
                                                                          query: {
                                                                              payments: 1,
                                                                          },
                                                                      },
                                                                  ),
                                                              }
                                                            : undefined
                                                    }
                                                    title={`Delete ${cost.name}?`}
                                                    description={
                                                        cost.occurrences_booked >
                                                        0
                                                            ? `Stopping books no further payments and keeps the ${cost.occurrences_booked} already booked in the accounts. Deleting removes those payments too: use it for a cost entered by mistake. Receipts attached to them go back to the list of receipts.`
                                                            : 'Nothing was booked for it yet.'
                                                    }
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Pagination page={recurring} noun="recurring costs" />
                    </>
                )}
            </Panel>

            <Panel
                title="Receipts"
                description={
                    receiptReading
                        ? 'Upload receipts and invoices, then book each one as a cost by hand or attach it to a cost that exists. For each receipt you can choose to have an AI service read it; what it reads is only a proposal that you check and change.'
                        : 'Upload receipts and invoices, then book each one as a cost or attach it to a cost that exists. To be able to have them read by an AI service, connect one under Settings → Connections.'
                }
            >
                <ReceiptUpload receiptReading={receiptReading} />

                {receipts.total > 0 && (
                    <div className="mt-4 rounded-lg border">
                        <ul className="divide-y">
                            {receipts.data.map((receipt) => (
                                <ReceiptRow
                                    key={receipt.id}
                                    receipt={receipt}
                                    costs={attachableCosts}
                                    currency={currency}
                                    categories={categories}
                                    receiptReading={receiptReading}
                                />
                            ))}
                        </ul>
                        <Pagination page={receipts} noun="receipts" />
                    </div>
                )}
            </Panel>

            {costs.total === 0 && filters.search === '' ? (
                <EmptyState
                    title="No overheads booked yet"
                    description="Book a one-off cost, or a recurring one such as a subscription."
                />
            ) : (
                <div className="grid gap-3">
                    <FilterBar
                        url={index.url()}
                        filters={filters}
                        search="Search cost, memo or category…"
                    />
                    {costs.total === 0 ? (
                        <EmptyState title="No cost matches" />
                    ) : (
                        <Panel
                            title="Booked costs"
                            description="Each is an ordinary expense and can be changed or deleted."
                            flush
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <SortableHead
                                            column="paid_timestamp"
                                            sorter={costSorter}
                                            first="desc"
                                        >
                                            Paid date
                                        </SortableHead>
                                        <SortableHead
                                            column="name"
                                            sorter={costSorter}
                                        >
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
                                        <TableHead className="w-28" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {costs.data.map((cost) => (
                                        <TableRow key={cost.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {date(cost.paid_timestamp)}
                                            </TableCell>
                                            <TableCell>
                                                {cost.source ??
                                                    cost.memo ??
                                                    '–'}
                                                {cost.recurring && (
                                                    <Badge
                                                        variant="info"
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
                                                {cost.converted_total ===
                                                null ? (
                                                    <Badge variant="destructive">
                                                        {money(
                                                            cost.total,
                                                            cost.currency,
                                                        )}{' '}
                                                        · no rate
                                                    </Badge>
                                                ) : (
                                                    <Amount
                                                        value={
                                                            cost.converted_total
                                                        }
                                                        currency={currency}
                                                    />
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end">
                                                    {cost.receipt_id && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            asChild
                                                        >
                                                            <a
                                                                href={ReceiptController.show.url(
                                                                    cost.receipt_id,
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                title="Open receipt"
                                                                aria-label={`Open the receipt of ${cost.source ?? cost.memo ?? 'this cost'}`}
                                                            >
                                                                <Paperclip className="text-muted-foreground" />
                                                            </a>
                                                        </Button>
                                                    )}
                                                    <FormDialog
                                                        title={`Edit ${cost.source ?? cost.memo ?? 'cost'}`}
                                                        description="An overhead is a cost of running the label that belongs to no release."
                                                        form={LabelCostController.update.form(
                                                            cost.id,
                                                        )}
                                                        submitLabel="Save cost"
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Edit cost"
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
                                                                    errors={
                                                                        errors
                                                                    }
                                                                    amountLabel="Amount"
                                                                    refund={
                                                                        cost.refund
                                                                    }
                                                                    cost={{
                                                                        name:
                                                                            cost.source ??
                                                                            cost.memo ??
                                                                            '',
                                                                        amount: cost.amount,
                                                                        currency:
                                                                            cost.currency,
                                                                        category:
                                                                            cost.category,
                                                                        memo: cost.memo,
                                                                        conversion_rate:
                                                                            cost.conversion_rate,
                                                                    }}
                                                                />
                                                                <Field
                                                                    label="Paid date"
                                                                    htmlFor="paid_timestamp"
                                                                    error={
                                                                        errors.paid_timestamp
                                                                    }
                                                                    required
                                                                >
                                                                    <Input
                                                                        id="paid_timestamp"
                                                                        name="paid_timestamp"
                                                                        type="date"
                                                                        defaultValue={
                                                                            cost.paid_timestamp
                                                                        }
                                                                        required
                                                                    />
                                                                </Field>
                                                            </>
                                                        )}
                                                    </FormDialog>
                                                    <DeleteButton
                                                        url={TransactionController.destroy.url(
                                                            cost.id,
                                                        )}
                                                        title={`Delete ${cost.source ?? cost.memo ?? 'this cost'}?`}
                                                        description="It is removed from the label result and from the period it counted in. A recurring cost will not book this date again. A receipt attached to it goes back to the list of receipts."
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <Pagination page={costs} noun="costs" />
                        </Panel>
                    )}
                </div>
            )}
        </Page>
    );
}

LabelCosts.layout = { breadcrumbs: [{ title: 'Overheads', href: index() }] };
