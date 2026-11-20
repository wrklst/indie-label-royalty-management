import { cn } from '@/lib/utils';
import { SegmentedBar, partColours } from '@/components/segmented-bar';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { CheckField } from '@/components/field';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { RecordLink } from '@/components/record-link';
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
import { percent } from '@/lib/format';
import { create, edit, index } from '@/routes/contracts';
import type { Paginated } from '@/types';

type Contract = {
    id: number;
    name: string;
    memo: string | null;
    releases_count: number;
    tracks_count: number;
    items_count: number;
    children_count: number;
    parents_count: number;
    splits: {
        payee: string | null;
        payee_id: number | null;
        split_amount: string;
        ledger: string | null;
    }[];
};

export default function Contracts({
    contracts,
    filters,
}: {
    contracts: Paginated<Contract>;
    filters: {
        search: string;
        unused: boolean;
        sort: string | null;
        direction: SortDirection | null;
    };
}) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);

    return (
        <Page
            title="Contracts"
            description="A contract says who gets which share of the net income. Assign it to a release, or to a single track when that track has a different deal — a remix, for example. What is not split stays with the label."
            actions={
                <Button asChild>
                    <Link href={create()}>
                        <Plus />
                        New contract
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-wrap items-center gap-4">
                <Input
                    type="search"
                    placeholder="Search contracts…"
                    defaultValue={filters.search}
                    onChange={(event) =>
                        apply({ search: event.target.value }, true)
                    }
                    className="max-w-sm"
                />
                <CheckField
                    label="Not used anywhere"
                    checked={filters.unused}
                    onChange={(event) =>
                        apply({ unused: event.target.checked })
                    }
                />
            </div>

            {contracts.total === 0 ? (
                <EmptyState
                    title={
                        filters.search || filters.unused
                            ? 'No contract matches'
                            : 'No contracts yet'
                    }
                    description={
                        filters.search || filters.unused
                            ? undefined
                            : 'Without contracts all income stays with the label. Create one per deal, e.g. "Artist album deal 50/50".'
                    }
                    action={
                        !filters.search && !filters.unused ? (
                            <Button asChild>
                                <Link href={create()}>New contract</Link>
                            </Button>
                        ) : undefined
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Contract
                                </SortableHead>
                                <TableHead>Splits</TableHead>
                                <TableHead className="text-right">
                                    Label keeps
                                </TableHead>
                                <SortableHead
                                    column="usage"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Used by
                                </SortableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {contracts.data.map((contract) => {
                                const paid = contract.splits.reduce(
                                    (sum, split) =>
                                        sum + Number(split.split_amount),
                                    0,
                                );
                                const segments = [
                                    ...contract.splits.map((split) => ({
                                        label: split.payee ?? 'Payee',
                                        value: Number(split.split_amount),
                                    })),
                                    {
                                        label: 'Label',
                                        value: Math.max(0, 1 - paid),
                                        remainder: true,
                                    },
                                ];
                                const colours = partColours(segments);
                                const used =
                                    contract.releases_count +
                                    contract.tracks_count +
                                    contract.items_count +
                                    contract.parents_count;

                                return (
                                    <TableRow key={contract.id}>
                                        <TableCell>
                                            <Link
                                                href={edit(contract.id)}
                                                className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                            >
                                                {contract.name}
                                            </Link>
                                            {contract.children_count > 0 && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    prorated ·{' '}
                                                    {contract.children_count}{' '}
                                                    contracts
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {contract.children_count > 0 ? (
                                                <span className="text-muted-foreground">
                                                    From child contracts
                                                </span>
                                            ) : contract.splits.length === 0 ? (
                                                <Badge variant="warning">
                                                    no splits
                                                </Badge>
                                            ) : (
                                                <div className="flex flex-wrap gap-x-3 gap-y-1">
                                                    <SegmentedBar
                                                        compact
                                                        className="w-full max-w-xs"
                                                        format={(value) =>
                                                            percent(value)
                                                        }
                                                        segments={segments}
                                                    />
                                                    {contract.splits.map(
                                                        (split, position) => (
                                                            <span
                                                                key={position}
                                                                className="inline-flex items-baseline gap-1.5"
                                                            >
                                                                <span
                                                                    aria-hidden
                                                                    data-split-dot
                                                                    className={cn(
                                                                        'size-2.5 shrink-0 self-center rounded-full',
                                                                        colours[
                                                                            position
                                                                        ],
                                                                    )}
                                                                />
                                                                <RecordLink
                                                                    to="payee"
                                                                    id={
                                                                        split.payee_id
                                                                    }
                                                                >
                                                                    {
                                                                        split.payee
                                                                    }
                                                                </RecordLink>{' '}
                                                                <span className="text-muted-foreground tabular-nums">
                                                                    {percent(
                                                                        split.split_amount,
                                                                    )}
                                                                </span>
                                                                {split.ledger && (
                                                                    <span className="text-muted-foreground">
                                                                        {' '}
                                                                        →{' '}
                                                                        {
                                                                            split.ledger
                                                                        }
                                                                    </span>
                                                                )}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {contract.children_count > 0 ? (
                                                '–'
                                            ) : paid > 1.0000001 ? (
                                                <Badge variant="destructive">
                                                    over 100%
                                                </Badge>
                                            ) : (
                                                percent(1 - paid)
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {used === 0
                                                ? 'Not used'
                                                : [
                                                      contract.releases_count &&
                                                          `${contract.releases_count} release${contract.releases_count === 1 ? '' : 's'}`,
                                                      contract.tracks_count &&
                                                          `${contract.tracks_count} track${contract.tracks_count === 1 ? '' : 's'}`,
                                                      contract.items_count &&
                                                          `${contract.items_count} item${contract.items_count === 1 ? '' : 's'}`,
                                                      contract.parents_count &&
                                                          `${contract.parents_count} prorated contract${contract.parents_count === 1 ? '' : 's'}`,
                                                  ]
                                                      .filter(Boolean)
                                                      .join(', ')}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                    <Pagination page={contracts} noun="contracts" />
                </Panel>
            )}
        </Page>
    );
}

Contracts.layout = { breadcrumbs: [{ title: 'Contracts', href: index() }] };
