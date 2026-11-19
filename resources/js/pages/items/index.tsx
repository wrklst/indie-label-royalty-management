import { Link2 } from 'lucide-react';
import { useState } from 'react';
import ItemController from '@/actions/App/Http/Controllers/ItemController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { CheckField, Field, SelectInput } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { RecordLink } from '@/components/record-link';
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
import { index } from '@/routes/items';
import type { Option, Paginated, TrackOption } from '@/types';

type Item = {
    id: number;
    name: string;
    artist_name: string | null;
    track_name: string | null;
    catalog_number: string | null;
    isrc: string | null;
    upc: string | null;
    format_group: string | null;
    is_additionals: boolean;
    release_id: number | null;
    track_id: number | null;
    contract_id: number | null;
    transactions_count: number;
    release: { id: number; name: string; catalog_number: string | null } | null;
    track: Option | null;
    contract: Option | null;
    total: string;
};

type Props = {
    items: Paginated<Item>;
    filters: {
        search: string;
        unassigned: boolean;
        release: number | null;
        sort: string | null;
        direction: SortDirection | null;
    };
    unassignedCount: number;
    releaseOptions: Option[];
    trackOptions: TrackOption[];
    contractOptions: Option[];
};

function AssignFields({
    item,
    releaseOptions,
    trackOptions,
    contractOptions,
    errors,
}: {
    item: Item;
    releaseOptions: Option[];
    trackOptions: TrackOption[];
    contractOptions: Option[];
    errors: Record<string, string>;
}) {
    const [releaseId, setReleaseId] = useState<number | ''>(
        item.release_id ?? '',
    );

    return (
        <>
            <div className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                From the file:{' '}
                {[
                    item.artist_name,
                    item.name,
                    item.isrc && `ISRC ${item.isrc}`,
                    item.upc && `UPC ${item.upc}`,
                    item.catalog_number,
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </div>
            <Field
                label="Release"
                htmlFor="release_id"
                error={errors.release_id}
            >
                <SelectInput
                    id="release_id"
                    name="release_id"
                    value={releaseId}
                    onChange={(event) =>
                        setReleaseId(
                            event.target.value
                                ? Number(event.target.value)
                                : '',
                        )
                    }
                    options={releaseOptions}
                    placeholder="Unassigned"
                />
            </Field>
            <Field
                label="Track"
                htmlFor="track_id"
                error={errors.track_id}
                hint="Leave empty when the item is the whole release, e.g. an album download."
            >
                <SelectInput
                    id="track_id"
                    name="track_id"
                    defaultValue={item.track_id ?? ''}
                    options={trackOptions.filter(
                        (track) => track.release_id === releaseId,
                    )}
                    placeholder="Whole release"
                    disabled={releaseId === ''}
                />
            </Field>
            <Field
                label="Contract override"
                htmlFor="contract_id"
                error={errors.contract_id}
                hint="Only for items with their own deal, such as a licensed vinyl run. Otherwise the track or release contract applies."
            >
                <SelectInput
                    id="contract_id"
                    name="contract_id"
                    defaultValue={item.contract_id ?? ''}
                    options={contractOptions}
                    placeholder="Use track / release contract"
                />
            </Field>
        </>
    );
}

export default function Items({
    items,
    filters,
    unassignedCount,
    releaseOptions,
    trackOptions,
    contractOptions,
}: Props) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);
    const filtered =
        filters.search !== '' || filters.unassigned || filters.release !== null;

    return (
        <Page
            title="Items"
            description="An item is one sellable thing as it appears in statements: a track or release in a format group. Imports book transactions on items; assigning an item to a release moves all its transactions with it."
        >
            {unassignedCount > 0 && !filters.unassigned && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <span>
                        {unassignedCount} item
                        {unassignedCount === 1 ? ' is' : 's are'} not assigned
                        to a release. Their income stays with the label until
                        you assign them.
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => apply({ unassigned: true })}
                    >
                        Show unassigned
                    </Button>
                </div>
            )}

            <div className="flex flex-wrap items-center gap-3">
                <Input
                    type="search"
                    placeholder="Search name, ISRC, UPC…"
                    defaultValue={filters.search}
                    onChange={(event) =>
                        apply({ search: event.target.value }, true)
                    }
                    className="max-w-sm"
                />
                <SelectInput
                    aria-label="Release"
                    value={filters.release ?? ''}
                    onChange={(event) =>
                        apply({
                            release: event.target.value
                                ? Number(event.target.value)
                                : null,
                        })
                    }
                    options={releaseOptions}
                    placeholder="All releases"
                    className="w-72"
                />
                <CheckField
                    label="Unassigned only"
                    checked={filters.unassigned}
                    onChange={(event) =>
                        apply({ unassigned: event.target.checked })
                    }
                />
            </div>

            {items.total === 0 ? (
                <EmptyState
                    title={
                        filters.unassigned
                            ? 'Everything is assigned'
                            : filtered
                              ? 'No item matches'
                              : 'No items yet'
                    }
                    description={
                        filtered
                            ? undefined
                            : 'Items appear when you import statements.'
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Item
                                </SortableHead>
                                <SortableHead
                                    column="format_group"
                                    sorter={sorter}
                                >
                                    Format group
                                </SortableHead>
                                <SortableHead column="release" sorter={sorter}>
                                    Release
                                </SortableHead>
                                <SortableHead column="isrc" sorter={sorter}>
                                    ISRC / UPC
                                </SortableHead>
                                <SortableHead column="contract" sorter={sorter}>
                                    Contract override
                                </SortableHead>
                                <SortableHead
                                    column="transactions_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Transactions
                                </SortableHead>
                                <SortableHead
                                    column="total"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Total
                                </SortableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <span
                                            className={
                                                item.is_additionals
                                                    ? 'text-muted-foreground'
                                                    : 'font-medium'
                                            }
                                        >
                                            {item.name}
                                        </span>
                                        {item.artist_name && (
                                            <span className="block text-xs text-muted-foreground">
                                                {item.artist_name}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {item.format_group ?? '–'}
                                    </TableCell>
                                    <TableCell>
                                        {item.release ? (
                                            <RecordLink
                                                to="release"
                                                id={item.release.id}
                                            >
                                                {item.release.name}
                                            </RecordLink>
                                        ) : (
                                            <Badge variant="destructive">
                                                Unassigned
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                        {item.isrc ?? item.upc ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {item.contract ? (
                                            <RecordLink
                                                to="contract"
                                                id={item.contract.id}
                                            >
                                                {item.contract.name}
                                            </RecordLink>
                                        ) : (
                                            '–'
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.transactions_count.toLocaleString(
                                            'en-GB',
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={item.total} />
                                    </TableCell>
                                    <TableCell>
                                        {!item.is_additionals && (
                                            <div className="flex justify-end">
                                                <FormDialog
                                                    title="Assign item"
                                                    description={`${item.transactions_count.toLocaleString('en-GB')} transaction(s) follow the item to the release and track you choose.`}
                                                    form={ItemController.update.form(
                                                        item.id,
                                                    )}
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Assign ${item.name}`}
                                                        >
                                                            <Link2 className="text-muted-foreground" />
                                                        </Button>
                                                    }
                                                >
                                                    {(errors) => (
                                                        <AssignFields
                                                            item={item}
                                                            releaseOptions={
                                                                releaseOptions
                                                            }
                                                            trackOptions={
                                                                trackOptions
                                                            }
                                                            contractOptions={
                                                                contractOptions
                                                            }
                                                            errors={errors}
                                                        />
                                                    )}
                                                </FormDialog>
                                                {item.transactions_count ===
                                                    0 && (
                                                    <DeleteButton
                                                        url={ItemController.destroy.url(
                                                            item.id,
                                                        )}
                                                        title={`Delete ${item.name}?`}
                                                        description="The item has no transactions and can be removed safely."
                                                    />
                                                )}
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={items} noun="items" />
                </Panel>
            )}
        </Page>
    );
}

Items.layout = { breadcrumbs: [{ title: 'Items', href: index() }] };
