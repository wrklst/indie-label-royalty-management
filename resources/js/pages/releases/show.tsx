import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { Amount } from '@/components/amount';
import { Page, Panel, Stat } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { date, quantity } from '@/lib/format';
import { edit as editContract } from '@/routes/contracts';
import { edit, index } from '@/routes/releases';
import {
    create as createTransaction,
    index as transactions,
} from '@/routes/transactions';
import type { Option } from '@/types';

type Props = {
    release: {
        id: number;
        name: string;
        catalog_number: string | null;
        upc: string | null;
        released_on: string | null;
        artist: string | null;
        notes: string | null;
        contract: Option | null;
        tracks: {
            id: number;
            name: string;
            isrc: string | null;
            track_number: number | null;
            artist: string | null;
            contract: Option | null;
            income: string;
            quantity: string;
        }[];
        items: {
            id: number;
            name: string;
            format_group: string | null;
            is_additionals: boolean;
            track: string | null;
            contract: Option | null;
        }[];
    };
    totals: {
        income: string;
        expense: string;
        transactions: number;
        release_level_income: string;
    };
    retailers: { retailer: string; total: string; quantity: string }[];
};

export default function ShowRelease({ release, totals, retailers }: Props) {
    return (
        <Page
            title={release.name}
            description={[
                release.artist,
                release.catalog_number,
                release.upc && `UPC ${release.upc}`,
                release.released_on && `released ${date(release.released_on)}`,
            ]
                .filter(Boolean)
                .join(' · ')}
            actions={
                <>
                    <Button variant="outline" asChild>
                        <Link
                            href={createTransaction({
                                query: { type: 'expense', release: release.id },
                            })}
                        >
                            <Plus />
                            Add expense
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={edit(release.id)}>
                            <Pencil />
                            Edit
                        </Link>
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="Income to date"
                    value={<Amount value={totals.income} />}
                />
                <Stat
                    label="Expenses to date"
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
                    label="Contract"
                    value={
                        release.contract ? (
                            <Link
                                href={editContract(release.contract.id)}
                                className="text-base underline decoration-muted-foreground/40 underline-offset-4"
                            >
                                {release.contract.name}
                            </Link>
                        ) : (
                            <span className="text-base">None</span>
                        )
                    }
                    hint={
                        release.contract
                            ? undefined
                            : 'The label keeps all income from tracks without their own contract.'
                    }
                />
            </div>

            <Panel
                title="Tracks"
                flush
                actions={
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={transactions({
                                query: { release: release.id },
                            })}
                        >
                            {totals.transactions.toLocaleString('en-GB')}{' '}
                            transactions
                        </Link>
                    </Button>
                }
            >
                {release.tracks.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No tracks on this release.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">#</TableHead>
                                <TableHead>Title</TableHead>
                                <TableHead>Artist</TableHead>
                                <TableHead>ISRC</TableHead>
                                <TableHead>Contract</TableHead>
                                <TableHead className="text-right">
                                    Units
                                </TableHead>
                                <TableHead className="text-right">
                                    Income
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {release.tracks.map((track) => (
                                <TableRow key={track.id}>
                                    <TableCell className="text-muted-foreground tabular-nums">
                                        {track.track_number ?? '–'}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {track.name}
                                    </TableCell>
                                    <TableCell>{track.artist ?? '–'}</TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {track.isrc ?? '–'}
                                    </TableCell>
                                    <TableCell>
                                        {track.contract ? (
                                            <Link
                                                href={editContract(
                                                    track.contract.id,
                                                )}
                                                className="underline decoration-muted-foreground/40 underline-offset-4"
                                            >
                                                {track.contract.name}
                                            </Link>
                                        ) : release.contract ? (
                                            <span className="text-muted-foreground">
                                                Release contract
                                            </span>
                                        ) : (
                                            <Badge variant="outline">
                                                none
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {quantity(track.quantity)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={track.income} />
                                    </TableCell>
                                </TableRow>
                            ))}
                            {Number(totals.release_level_income) !== 0 && (
                                <TableRow>
                                    <TableCell />
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground"
                                    >
                                        Booked on the release as a whole (album
                                        sales, additionals)
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount
                                            value={totals.release_level_income}
                                        />
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                )}
            </Panel>

            <div className="grid gap-6 xl:grid-cols-2">
                <Panel title="Income by retailer" flush>
                    {retailers.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No income yet.
                        </p>
                    ) : (
                        <Table>
                            <TableBody>
                                {retailers.map((row) => (
                                    <TableRow key={row.retailer}>
                                        <TableCell>{row.retailer}</TableCell>
                                        <TableCell className="text-right text-muted-foreground tabular-nums">
                                            {quantity(row.quantity)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Amount value={row.total} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Panel>
                <Panel
                    title="Items"
                    description="What transactions are booked against: one per track and format group, plus [Additionals] for manual entries."
                    flush
                >
                    <Table>
                        <TableBody>
                            {release.items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell
                                        className={
                                            item.is_additionals
                                                ? 'text-muted-foreground'
                                                : ''
                                        }
                                    >
                                        {item.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {item.format_group ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground">
                                        {item.contract?.name ?? ''}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>
            </div>

            {release.notes && <Panel title="Notes">{release.notes}</Panel>}
        </Page>
    );
}

ShowRelease.layout = {
    breadcrumbs: [
        { title: 'Releases', href: index() },
        { title: 'Release', href: '#' },
    ],
};
