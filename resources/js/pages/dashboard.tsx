import { Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { Amount } from '@/components/amount';
import { SelectInput } from '@/components/field';
import { EmptyState, Page, Panel, Stat } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/hooks/use-filters';
import { country, month, quantity } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as catalog } from '@/routes/catalog';
import {
    create as createImport,
    index as imports,
} from '@/routes/data-imports';
import { index as items } from '@/routes/items';
import { show as showRelease } from '@/routes/releases';
import { index as transactions } from '@/routes/transactions';
import type { Breakdown } from '@/types';

type Props = {
    filters: { from: string; to: string; date: 'sold' | 'paid' };
    totals: { income: string; expense: string; transactions: number };
    months: { label: string; total: string }[];
    retailers: Breakdown[];
    formats: Breakdown[];
    regions: Breakdown[];
    releases: (Breakdown & { id: number })[];
    attention: {
        unassigned_items: number;
        releases_without_contract: number;
        unconverted: number;
        imports: number;
    };
    hasData: boolean;
};

function BreakdownList({
    rows,
    render,
}: {
    rows: Breakdown[];
    render?: (row: Breakdown) => React.ReactNode;
}) {
    const max = Math.max(...rows.map((row) => Number(row.total)), 0);

    if (rows.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No income in this range.
            </p>
        );
    }

    return (
        <ul className="grid gap-2.5">
            {rows.map((row, position) => (
                <li
                    key={`${row.label}-${position}`}
                    className="grid gap-1 text-sm"
                >
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="min-w-0 truncate">
                            {render ? render(row) : (row.label ?? 'Unknown')}
                        </span>
                        <span className="flex shrink-0 items-baseline gap-3">
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {quantity(row.quantity)}
                            </span>
                            <Amount value={row.total} />
                        </span>
                    </div>
                    <div className="h-1 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-foreground/60"
                            style={{
                                width: `${max > 0 ? Math.max((Number(row.total) / max) * 100, 0) : 0}%`,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function Dashboard({
    filters,
    totals,
    months,
    retailers,
    formats,
    regions,
    releases,
    attention,
    hasData,
}: Props) {
    const { apply } = useFilters(dashboard.url(), filters);
    const peak = Math.max(...months.map((row) => Number(row.total)), 0);
    const todo = [
        attention.unassigned_items > 0 && {
            text: `${attention.unassigned_items} item${attention.unassigned_items === 1 ? '' : 's'} not assigned to a release`,
            href: items({ query: { unassigned: 1 } }),
        },
        attention.releases_without_contract > 0 && {
            text: `${attention.releases_without_contract} release${attention.releases_without_contract === 1 ? '' : 's'} without a contract`,
            href: catalog(),
        },
        attention.unconverted > 0 && {
            text: `${attention.unconverted} transaction${attention.unconverted === 1 ? '' : 's'} without a conversion rate`,
            href: transactions({ query: { unconverted: 1 } }),
        },
        attention.imports > 0 && {
            text: `${attention.imports} import${attention.imports === 1 ? '' : 's'} flagged for cleanup`,
            href: imports(),
        },
    ].filter((entry) => entry !== false);

    if (!hasData) {
        return (
            <Page title="Dashboard">
                <EmptyState
                    title="Nothing imported yet"
                    description="Upload a statement from Zebralution, Bandcamp or your neighbouring rights society. Releases, tracks and artists are created from the file."
                    action={
                        <Button asChild>
                            <Link href={createImport()}>
                                <Upload />
                                Import a statement
                            </Link>
                        </Button>
                    }
                />
            </Page>
        );
    }

    return (
        <Page
            title="Dashboard"
            actions={
                <>
                    <SelectInput
                        aria-label="Date used"
                        value={filters.date}
                        onChange={(event) =>
                            apply({
                                date: event.target.value as 'sold' | 'paid',
                            })
                        }
                        className="w-40"
                    >
                        <option value="sold">By sale date</option>
                        <option value="paid">By paid date</option>
                    </SelectInput>
                    <Input
                        aria-label="From"
                        type="date"
                        key={filters.from}
                        defaultValue={filters.from}
                        onChange={(event) =>
                            apply({ from: event.target.value }, true)
                        }
                        className="w-40"
                    />
                    <Input
                        aria-label="To"
                        type="date"
                        key={filters.to}
                        defaultValue={filters.to}
                        onChange={(event) =>
                            apply({ to: event.target.value }, true)
                        }
                        className="w-40"
                    />
                </>
            }
        >
            {todo.length > 0 && (
                <div className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <p className="font-medium">
                        Needs attention before the next statements
                    </p>
                    <ul className="mt-1 grid gap-0.5">
                        {todo.map((entry) => (
                            <li key={entry.text}>
                                <Link
                                    href={entry.href}
                                    className="underline underline-offset-4"
                                >
                                    {entry.text}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Income" value={<Amount value={totals.income} />} />
                <Stat
                    label="Expenses"
                    value={<Amount value={totals.expense} />}
                />
                <Stat
                    label="Net"
                    value={
                        <Amount
                            value={
                                Number(totals.income) + Number(totals.expense)
                            }
                        />
                    }
                />
                <Stat
                    label="Transactions"
                    value={totals.transactions.toLocaleString('en-GB')}
                />
            </div>

            <Panel title="Income by month">
                {months.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No income in this range.
                    </p>
                ) : (
                    <div className="flex h-44 items-end gap-2">
                        {months.map((row) => (
                            <div
                                key={row.label}
                                className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
                                title={`${row.label}: ${Number(row.total).toFixed(2)}`}
                            >
                                <span className="text-[10px] whitespace-nowrap text-muted-foreground tabular-nums">
                                    {Number(row.total).toLocaleString('en-GB', {
                                        maximumFractionDigits:
                                            peak >= 100 ? 0 : 2,
                                    })}
                                </span>
                                <div
                                    className="w-full rounded-t bg-foreground/70"
                                    style={{
                                        height: `${peak > 0 ? Math.max((Number(row.total) / peak) * 100, 0) * 0.8 : 0}%`,
                                        minHeight: 2,
                                    }}
                                />
                                <span className="text-[10px] whitespace-nowrap text-muted-foreground">
                                    {month(row.label)}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </Panel>

            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Top releases">
                    <BreakdownList
                        rows={releases}
                        render={(row) => (
                            <Link
                                href={showRelease(
                                    (row as Breakdown & { id: number }).id,
                                )}
                                className="underline decoration-muted-foreground/40 underline-offset-4"
                            >
                                {row.label}
                            </Link>
                        )}
                    />
                </Panel>
                <Panel title="Retailers">
                    <BreakdownList rows={retailers} />
                </Panel>
                <Panel title="Formats">
                    <BreakdownList rows={formats} />
                </Panel>
                <Panel title="Countries">
                    <BreakdownList
                        rows={regions}
                        render={(row) => country(row.label)}
                    />
                </Panel>
            </div>
        </Page>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
