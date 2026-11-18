import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { CheckField, SelectInput } from '@/components/field';
import { EmptyState, Page, Panel } from '@/components/page';
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
import type { SortDirection } from '@/hooks/use-table-sort';
import { date, quantity } from '@/lib/format';
import { create, edit, index } from '@/routes/transactions';
import type { Option, Paginated } from '@/types';

type Transaction = {
    id: number;
    type: 'income' | 'expense' | 'transfer';
    timestamp: string;
    paid_timestamp: string;
    total: string;
    converted_total: string | null;
    currency: string;
    quantity: string | null;
    category: string | null;
    format: string | null;
    region: string | null;
    retailer: string | null;
    memo: string | null;
    recoupable: boolean;
    release: Option | null;
    track: string | null;
    payee: string | null;
};

type Filters = {
    search?: string;
    type?: string;
    release?: string;
    retailer?: string;
    import?: string;
    from?: string;
    to?: string;
    date?: string;
    unassigned?: string;
    unconverted?: string;
    sort?: string | null;
    direction?: SortDirection | null;
};

type Props = {
    transactions: Paginated<Transaction>;
    summary: { transactions: number; total: string; quantity: string };
    filters: Filters;
    releaseOptions: Option[];
    retailerOptions: string[];
    importOptions: Option[];
};

const isOn = (value: string | undefined) => value === '1' || value === 'true';

export default function Transactions({
    transactions,
    summary,
    filters,
    releaseOptions,
    retailerOptions,
    importOptions,
}: Props) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);
    const filtered = Object.entries(filters).some(
        ([key, value]) =>
            key !== 'sort' &&
            key !== 'direction' &&
            value !== undefined &&
            value !== null &&
            value !== '',
    );

    return (
        <Page
            title="Transactions"
            description="Every line of income, every expense and every transfer. Expenses are negative; a negative transfer was given to the payee. A one-time payment is an expense the label bears alone."
            actions={
                <>
                    <Button variant="outline" asChild>
                        <Link
                            href={create({
                                query: { type: 'expense', recoupable: 0 },
                            })}
                        >
                            <Plus />
                            One-time payment
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={create({ query: { type: 'expense' } })}>
                            <Plus />
                            Expense
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={create({ query: { type: 'transfer' } })}>
                            <Plus />
                            Transfer
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={create({ query: { type: 'income' } })}>
                            <Plus />
                            Income
                        </Link>
                    </Button>
                </>
            }
        >
            <div className="grid gap-3">
                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        type="search"
                        placeholder="Search memo, retailer, track, release, ISRC…"
                        defaultValue={filters.search ?? ''}
                        onChange={(event) =>
                            apply({ search: event.target.value }, true)
                        }
                        className="max-w-sm"
                    />
                    <SelectInput
                        aria-label="Type"
                        value={filters.type ?? ''}
                        onChange={(event) =>
                            apply({ type: event.target.value })
                        }
                        placeholder="All types"
                        options={['income', 'expense', 'transfer']}
                        className="w-36 capitalize"
                    />
                    <SelectInput
                        aria-label="Release"
                        value={filters.release ?? ''}
                        onChange={(event) =>
                            apply({ release: event.target.value })
                        }
                        placeholder="All releases"
                        options={releaseOptions}
                        className="w-64"
                    />
                    <SelectInput
                        aria-label="Retailer"
                        value={filters.retailer ?? ''}
                        onChange={(event) =>
                            apply({ retailer: event.target.value })
                        }
                        placeholder="All retailers"
                        options={retailerOptions}
                        className="w-48"
                    />
                    <SelectInput
                        aria-label="Import"
                        value={filters.import ?? ''}
                        onChange={(event) =>
                            apply({ import: event.target.value })
                        }
                        placeholder="All imports"
                        options={importOptions}
                        className="w-56"
                    />
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <SelectInput
                        aria-label="Date used"
                        value={filters.date ?? ''}
                        onChange={(event) =>
                            apply({ date: event.target.value })
                        }
                        className="w-36"
                    >
                        <option value="">Sale date</option>
                        <option value="paid">Paid date</option>
                    </SelectInput>
                    <Input
                        aria-label="From"
                        type="date"
                        key={filters.from ?? ''}
                        defaultValue={filters.from ?? ''}
                        onChange={(event) =>
                            apply({ from: event.target.value }, true)
                        }
                        className="w-40"
                    />
                    <span className="text-sm text-muted-foreground">to</span>
                    <Input
                        aria-label="To"
                        type="date"
                        key={filters.to ?? ''}
                        defaultValue={filters.to ?? ''}
                        onChange={(event) =>
                            apply({ to: event.target.value }, true)
                        }
                        className="w-40"
                    />
                    <CheckField
                        label="Unassigned"
                        checked={isOn(filters.unassigned)}
                        onChange={(event) =>
                            apply({
                                unassigned: event.target.checked ? '1' : '',
                            })
                        }
                    />
                    <CheckField
                        label="Unconverted"
                        checked={isOn(filters.unconverted)}
                        onChange={(event) =>
                            apply({
                                unconverted: event.target.checked ? '1' : '',
                            })
                        }
                    />
                    {filtered && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={index()}>Clear filters</Link>
                        </Button>
                    )}
                </div>
            </div>

            {transactions.total === 0 ? (
                <EmptyState
                    title={
                        filtered
                            ? 'No transaction matches these filters'
                            : 'No transactions yet'
                    }
                    description={
                        filtered
                            ? undefined
                            : 'Import a statement or add income, an expense or a transfer by hand.'
                    }
                />
            ) : (
                <Panel flush>
                    <div className="flex flex-wrap gap-x-8 gap-y-1 border-b px-4 py-3 text-sm">
                        <span>
                            <span className="text-muted-foreground">
                                Matching{' '}
                            </span>
                            <span className="font-medium tabular-nums">
                                {summary.transactions.toLocaleString('en-GB')}
                            </span>
                        </span>
                        <span>
                            <span className="text-muted-foreground">
                                Units{' '}
                            </span>
                            <span className="font-medium tabular-nums">
                                {quantity(summary.quantity)}
                            </span>
                        </span>
                        <span>
                            <span className="text-muted-foreground">
                                Total{' '}
                            </span>
                            <Amount
                                value={summary.total}
                                className="font-medium"
                            />
                        </span>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="timestamp"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Sold
                                </SortableHead>
                                <SortableHead
                                    column="paid_timestamp"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Paid
                                </SortableHead>
                                <SortableHead column="type" sorter={sorter}>
                                    Type
                                </SortableHead>
                                <SortableHead column="release" sorter={sorter}>
                                    Release / track
                                </SortableHead>
                                <SortableHead column="retailer" sorter={sorter}>
                                    Retailer
                                </SortableHead>
                                <SortableHead column="format" sorter={sorter}>
                                    Format / category
                                </SortableHead>
                                <SortableHead column="region" sorter={sorter}>
                                    Region
                                </SortableHead>
                                <SortableHead
                                    column="quantity"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Units
                                </SortableHead>
                                <SortableHead
                                    column="converted_total"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Amount
                                </SortableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transactions.data.map((transaction) => (
                                <TableRow key={transaction.id}>
                                    <TableCell className="whitespace-nowrap">
                                        {date(transaction.timestamp)}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {date(transaction.paid_timestamp)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                transaction.type === 'income'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            className="capitalize"
                                        >
                                            {transaction.type}
                                        </Badge>
                                        {transaction.type === 'expense' &&
                                            !transaction.recoupable && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-1"
                                                    title="Borne by the label alone, not recouped from payees"
                                                >
                                                    label cost
                                                </Badge>
                                            )}
                                    </TableCell>
                                    <TableCell className="max-w-xs">
                                        {transaction.type === 'transfer' ? (
                                            <span>
                                                {transaction.payee}
                                                {transaction.release && (
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {
                                                            transaction.release
                                                                .name
                                                        }
                                                    </span>
                                                )}
                                            </span>
                                        ) : transaction.release ? (
                                            <span>
                                                <span className="block truncate">
                                                    {transaction.track ??
                                                        transaction.release
                                                            .name}
                                                </span>
                                                {transaction.track && (
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {
                                                            transaction.release
                                                                .name
                                                        }
                                                    </span>
                                                )}
                                            </span>
                                        ) : (
                                            <Badge variant="destructive">
                                                Unassigned
                                            </Badge>
                                        )}
                                        {transaction.memo && (
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {transaction.memo}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {transaction.retailer ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {transaction.format ??
                                            transaction.category ??
                                            '–'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {transaction.region ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {quantity(transaction.quantity)}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap">
                                        {transaction.converted_total ===
                                        null ? (
                                            <Badge variant="destructive">
                                                {Number(
                                                    transaction.total,
                                                ).toFixed(2)}{' '}
                                                {transaction.currency} · no rate
                                            </Badge>
                                        ) : (
                                            <Amount
                                                value={
                                                    transaction.converted_total
                                                }
                                                precise
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
                                                    href={edit(transaction.id)}
                                                    aria-label="Edit transaction"
                                                >
                                                    <Pencil className="text-muted-foreground" />
                                                </Link>
                                            </Button>
                                            <DeleteButton
                                                url={TransactionController.destroy.url(
                                                    transaction.id,
                                                )}
                                                title="Delete this transaction?"
                                                description="It is removed from every period and balance it counted in. Statements already generated keep their figures."
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={transactions} noun="transactions" />
                </Panel>
            )}
        </Page>
    );
}

Transactions.layout = {
    breadcrumbs: [{ title: 'Transactions', href: index() }],
};
