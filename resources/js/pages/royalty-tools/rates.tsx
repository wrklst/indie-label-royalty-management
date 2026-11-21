import { Link, setLayoutProps, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import RatesController from '@/actions/App/Http/Controllers/RoyaltyTools/RatesController';
import { CheckField, Field, SelectInput } from '@/components/field';
import InputError from '@/components/input-error';
import { EmptyState, Page, Panel } from '@/components/page';
import { RecordLink } from '@/components/record-link';
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
import { duration } from '@/lib/format';
import { index as mechanicals } from '@/routes/royalty-tools/mechanicals';
import { index as ppd } from '@/routes/royalty-tools/ppd';

type Tool = 'mechanicals' | 'ppd';

type Props = {
    tool: Tool;
    currency: string;
    release: {
        id: number;
        name: string;
        catalog_number: string | null;
        accounts_mechanicals: boolean;
        rate: string | null;
    };
    tracks: {
        id: number;
        name: string;
        track_number: number | null;
        duration: number | null;
        accounts_mechanicals: boolean | null;
        rate: string | null;
    }[];
    items: {
        id: number;
        name: string;
        format_group: string | null;
        track: string | null;
        rate: string | null;
    }[];
};

type TrackRow = Props['tracks'][number] & { position: number };
type ItemRow = Props['items'][number] & { position: number };

const TRACK_COLUMNS = {
    track_number: (row: TrackRow) => row.track_number,
    name: (row: TrackRow) => row.name,
    duration: (row: TrackRow) => row.duration,
    accounts: (row: TrackRow) => row.accounts_mechanicals,
    rate: (row: TrackRow) => row.rate,
};

const ITEM_COLUMNS = {
    name: (row: ItemRow) => row.name,
    track: (row: ItemRow) => row.track,
    format_group: (row: ItemRow) => row.format_group,
    rate: (row: ItemRow) => row.rate,
};

type Choice = 'follow' | 'yes' | 'no';

const choice = (value: boolean | null): Choice =>
    value === null ? 'follow' : value ? 'yes' : 'no';

function RateInput({
    label,
    value,
    onChange,
    placeholder,
    id,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    id?: string;
}) {
    return (
        <Input
            id={id}
            aria-label={label}
            type="number"
            step="any"
            min="0"
            className="w-32 text-right"
            placeholder={placeholder}
            value={value}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

export default function Rates({
    tool,
    currency,
    release,
    tracks,
    items,
}: Props) {
    const isMechanicals = tool === 'mechanicals';

    setLayoutProps({
        breadcrumbs: [
            isMechanicals
                ? { title: 'Mechanicals', href: mechanicals() }
                : { title: 'PPD', href: ppd() },
            { title: 'Rates', href: '#' },
        ],
    });

    const { rows: trackRows, sorter: trackSorter } = useTableSort(
        useMemo(
            () => tracks.map((track, position) => ({ ...track, position })),
            [tracks],
        ),
        TRACK_COLUMNS,
    );
    const { rows: itemRows, sorter: itemSorter } = useTableSort(
        useMemo(
            () => items.map((item, position) => ({ ...item, position })),
            [items],
        ),
        ITEM_COLUMNS,
    );

    const form = useForm({
        rate: release.rate ?? '',
        accounts_mechanicals: release.accounts_mechanicals,
        tracks: tracks.map((track) => ({
            id: track.id,
            rate: track.rate ?? '',
            accounts_mechanicals: choice(track.accounts_mechanicals),
        })),
        items: items.map((item) => ({ id: item.id, rate: item.rate ?? '' })),
    });
    const errors = form.errors as Record<string, string>;
    const inherited =
        form.data.rate === '' ? undefined : `${form.data.rate} (release)`;

    const setTrack = (
        position: number,
        changes: Partial<(typeof form.data.tracks)[number]>,
    ) =>
        form.setData(
            'tracks',
            form.data.tracks.map((track, at) =>
                at === position ? { ...track, ...changes } : track,
            ),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            rate: data.rate === '' ? null : data.rate,
            accounts_mechanicals: data.accounts_mechanicals,
            tracks: data.tracks.map((track) => ({
                id: track.id,
                rate: track.rate === '' ? null : track.rate,
                accounts_mechanicals:
                    track.accounts_mechanicals === 'follow'
                        ? null
                        : track.accounts_mechanicals === 'yes',
            })),
            items: data.items.map((item) => ({
                id: item.id,
                rate: item.rate === '' ? null : item.rate,
            })),
        }));
        form.put(RatesController.update.url({ tool, release: release.id }), {
            preserveScroll: true,
        });
    };

    return (
        <Page
            title={`${isMechanicals ? 'Mechanicals' : 'PPD'} rates: ${release.name}`}
            description={
                isMechanicals
                    ? `A rate is an amount in ${currency} per unit and per track. A track uses its own rate, else the release rate, else the statutory rate for its duration. A track that follows the release accounts mechanicals when the release does.`
                    : `A rate is an amount in ${currency} per unit sold. An item uses its own rate, else the rate of its track, else the release rate. Anything without a rate takes no part in PPD.`
            }
        >
            <form onSubmit={submit} className="flex flex-col gap-6">
                <Panel title="Release">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={
                                isMechanicals
                                    ? 'Mechanicals rate per unit'
                                    : 'PPD rate per unit'
                            }
                            htmlFor="rate"
                            error={errors.rate}
                            hint={
                                isMechanicals
                                    ? 'Applies to each track of the release. Empty: statutory rate.'
                                    : 'Applies to every sale of the release. Empty: no PPD unless a track or item has a rate.'
                            }
                        >
                            <RateInput
                                id="rate"
                                label="Release rate"
                                value={form.data.rate}
                                onChange={(value) =>
                                    form.setData('rate', value)
                                }
                            />
                        </Field>
                        {isMechanicals && (
                            <CheckField
                                id="accounts_mechanicals"
                                label="Account for mechanicals on this release"
                                hint="Tracks follow this unless they say otherwise."
                                checked={form.data.accounts_mechanicals}
                                onChange={(event) =>
                                    form.setData(
                                        'accounts_mechanicals',
                                        event.target.checked,
                                    )
                                }
                                className="self-center"
                            />
                        )}
                    </div>
                </Panel>

                <Panel title="Tracks" flush>
                    {tracks.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            This release has no tracks.
                            {isMechanicals &&
                                ' Mechanicals are calculated per track, so there is nothing to calculate yet.'}
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <SortableHead
                                        column="track_number"
                                        sorter={trackSorter}
                                        className="w-12"
                                    >
                                        #
                                    </SortableHead>
                                    <SortableHead
                                        column="name"
                                        sorter={trackSorter}
                                    >
                                        Track
                                    </SortableHead>
                                    {isMechanicals && (
                                        <>
                                            <SortableHead
                                                column="duration"
                                                sorter={trackSorter}
                                            >
                                                Length
                                            </SortableHead>
                                            <SortableHead
                                                column="accounts"
                                                sorter={trackSorter}
                                            >
                                                Mechanicals
                                            </SortableHead>
                                        </>
                                    )}
                                    <SortableHead
                                        column="rate"
                                        sorter={trackSorter}
                                        className="text-right"
                                    >
                                        Rate per unit
                                    </SortableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {trackRows.map((track) => (
                                    <TableRow key={track.id}>
                                        {/* position: the row's place in the form, whatever the table is sorted by */}
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {track.track_number ?? '–'}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            <RecordLink
                                                to="track"
                                                id={track.id}
                                            >
                                                {track.name}
                                            </RecordLink>
                                        </TableCell>
                                        {isMechanicals && (
                                            <>
                                                <TableCell className="tabular-nums">
                                                    {duration(track.duration)}
                                                </TableCell>
                                                <TableCell>
                                                    <SelectInput
                                                        aria-label={`Mechanicals for ${track.name}`}
                                                        className="w-44"
                                                        value={
                                                            form.data.tracks[
                                                                track.position
                                                            ]
                                                                .accounts_mechanicals
                                                        }
                                                        onChange={(event) =>
                                                            setTrack(
                                                                track.position,
                                                                {
                                                                    accounts_mechanicals:
                                                                        event
                                                                            .target
                                                                            .value as Choice,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <option value="follow">
                                                            Same as release
                                                        </option>
                                                        <option value="yes">
                                                            Account
                                                        </option>
                                                        <option value="no">
                                                            Do not account
                                                        </option>
                                                    </SelectInput>
                                                </TableCell>
                                            </>
                                        )}
                                        <TableCell>
                                            <div className="flex flex-col items-end">
                                                <RateInput
                                                    label={`Rate for ${track.name}`}
                                                    value={
                                                        form.data.tracks[
                                                            track.position
                                                        ].rate
                                                    }
                                                    onChange={(value) =>
                                                        setTrack(
                                                            track.position,
                                                            {
                                                                rate: value,
                                                            },
                                                        )
                                                    }
                                                    placeholder={inherited}
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `tracks.${track.position}.rate`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>

                {!isMechanicals && (
                    <Panel
                        title="Items"
                        description="Give an item its own rate when formats of the release have different dealer prices."
                        flush
                    >
                        {items.length === 0 ? (
                            <EmptyState
                                compact
                                title="This release has no items yet. They appear with the first import."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <SortableHead
                                            column="name"
                                            sorter={itemSorter}
                                        >
                                            Item
                                        </SortableHead>
                                        <SortableHead
                                            column="track"
                                            sorter={itemSorter}
                                        >
                                            Track
                                        </SortableHead>
                                        <SortableHead
                                            column="format_group"
                                            sorter={itemSorter}
                                        >
                                            Format group
                                        </SortableHead>
                                        <SortableHead
                                            column="rate"
                                            sorter={itemSorter}
                                            className="text-right"
                                        >
                                            Rate per unit
                                        </SortableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {itemRows.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.name}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {item.track ?? '–'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {item.format_group ?? '–'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col items-end">
                                                    <RateInput
                                                        label={`Rate for item ${item.name}`}
                                                        value={
                                                            form.data.items[
                                                                item.position
                                                            ].rate
                                                        }
                                                        onChange={(value) =>
                                                            form.setData(
                                                                'items',
                                                                form.data.items.map(
                                                                    (
                                                                        current,
                                                                        at,
                                                                    ) =>
                                                                        at ===
                                                                        item.position
                                                                            ? {
                                                                                  ...current,
                                                                                  rate: value,
                                                                              }
                                                                            : current,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `items.${item.position}.rate`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </Panel>
                )}

                <div className="flex items-center gap-3">
                    <Button disabled={form.processing}>Save rates</Button>
                    <Button variant="ghost" asChild>
                        <Link href={isMechanicals ? mechanicals() : ppd()}>
                            Back to {isMechanicals ? 'Mechanicals' : 'PPD'}
                        </Link>
                    </Button>
                </div>
            </form>
        </Page>
    );
}
