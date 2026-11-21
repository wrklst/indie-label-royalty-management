import { MoneyFlow } from '@/components/money-flow';
import type { Flow } from '@/components/money-flow';
import { Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { Amount } from '@/components/amount';
import { SelectInput } from '@/components/field';
import { IncomeReport, MoversPanel } from '@/components/income-report';
import type { Movers, Report } from '@/components/income-report';
import { EmptyState, Notice, Page, Stat } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/hooks/use-filters';
import { dashboard } from '@/routes';
import { index as catalog } from '@/routes/catalog';
import {
    create as createImport,
    index as imports,
} from '@/routes/data-imports';
import { index as items } from '@/routes/items';
import { index as transactions } from '@/routes/transactions';
import { count, plural } from '@/lib/format';

type Props = {
    filters: { from: string; to: string; date: 'sold' | 'paid' };
    totals: { income: string; expense: string; transactions: number };
    flow: Flow;
    report: Report;
    movers: Movers;
    attention: {
        unassigned_items: number;
        releases_without_contract: number;
        unconverted: number;
        imports: number;
    };
    hasData: boolean;
};

export default function Dashboard({
    filters,
    totals,
    flow,
    report,
    movers,
    attention,
    hasData,
}: Props) {
    const { apply } = useFilters(dashboard.url(), filters);
    const todo = [
        attention.unassigned_items > 0 && {
            text: `${plural(attention.unassigned_items, 'item')} not assigned to a release`,
            href: items({ query: { unassigned: 1 } }),
        },
        attention.releases_without_contract > 0 && {
            text: `${plural(attention.releases_without_contract, 'release')} without a contract`,
            href: catalog(),
        },
        attention.unconverted > 0 && {
            text: `${plural(attention.unconverted, 'transaction')} without a conversion rate`,
            href: transactions({ query: { unconverted: 1 } }),
        },
        attention.imports > 0 && {
            text: `${plural(attention.imports, 'import')} flagged for cleanup`,
            href: imports(),
        },
    ].filter((entry) => entry !== false);

    if (!hasData) {
        return (
            <Page title="Dashboard">
                <EmptyState
                    title="Nothing imported yet"
                    description="Upload a statement from your distributor, Bandcamp or your neighbouring rights society. Releases, tracks and artists are created from the file."
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
                <Notice title="Needs attention before the next statements">
                    <ul className="grid gap-0.5">
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
                </Notice>
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
                <Stat label="Transactions" value={count(totals.transactions)} />
            </div>

            <MoneyFlow flow={flow} />

            <IncomeReport report={report} empty="No income in this range." />

            <MoversPanel
                movers={movers}
                to="release"
                title="Releases that earned most"
            />
        </Page>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
