import { setLayoutProps } from '@inertiajs/react';
import { Amount } from '@/components/amount';
import { EmptyState, Page, Panel, Stat } from '@/components/page';
import type { PlayerTrack } from '@/components/player/player-store';
import { ReleaseActions } from '@/components/player/track-actions';
import { RecordLink } from '@/components/record-link';
import { IncomeReport, MoversPanel } from '@/components/income-report';
import type { Movers, Report } from '@/components/income-report';
import { SegmentedBar } from '@/components/segmented-bar';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { count, date, percent, plural } from '@/lib/format';
import { index } from '@/routes/artists';

type Props = {
    artist: {
        id: number;
        name: string;
        alias_of: { id: number; name: string } | null;
        aliases: { id: number; name: string }[];
    };
    totals: {
        income: string;
        expense: string;
        net: string;
        units: string;
        transactions: number;
        airplays: number;
    };
    releases: {
        id: number;
        name: string;
        catalog_number: string | null;
        released_on: string | null;
        income: string;
        player_tracks: PlayerTrack[];
    }[];
    report: Report;
    movers: Movers;
    contracts: {
        id: number;
        name: string;
        splits: {
            payee: { id: number; name: string } | null;
            split_amount: string;
        }[];
    }[];
    tracks: {
        id: number;
        name: string;
        isrc: string | null;
        releases: { id: number; name: string }[];
        airplays_count: number;
        income: string;
    }[];
};

export default function ShowArtist({
    artist,
    totals,
    releases,
    contracts,
    report,
    movers,
    tracks,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Artists', href: index() },
            { title: artist.name, href: '#' },
        ],
    });

    return (
        <Page
            title={artist.name}
            description={`${plural(releases.length, 'release')}, ${plural(tracks.length, 'track')}.`}
        >
            {(artist.alias_of || artist.aliases.length > 0) && (
                <p className="text-sm text-muted-foreground">
                    {artist.alias_of ? (
                        <>
                            Alias of{' '}
                            <RecordLink to="artist" id={artist.alias_of.id}>
                                {artist.alias_of.name}
                            </RecordLink>
                            . What statements book under this name belongs to
                            that artist.
                        </>
                    ) : (
                        <>
                            Also named on statements as:{' '}
                            {artist.aliases.map((alias, position) => (
                                <span key={alias.id}>
                                    {position > 0 && ' · '}
                                    <RecordLink to="artist" id={alias.id}>
                                        {alias.name}
                                    </RecordLink>
                                </span>
                            ))}
                        </>
                    )}
                </p>
            )}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="Income to date"
                    value={<Amount value={totals.income} />}
                    hint={`${count(totals.units)} units in ${count(totals.transactions)} transactions`}
                />
                <Stat
                    label="Expenses to date"
                    value={<Amount value={totals.expense} />}
                />
                <Stat
                    label="Net"
                    value={<Amount value={totals.net} />}
                    hint="Income less expenses, before any split"
                />
                <Stat
                    label="Airplay"
                    value={count(totals.airplays)}
                    hint="Plays known across all tracks"
                />
            </div>
            <p className="-mt-2 text-xs text-muted-foreground">
                Counts everything booked on this artist&apos;s releases and what
                their tracks earned on other releases. A release shared with
                other artists counts in full for each of them.
            </p>

            {releases.filter((release) => Number(release.income) > 0).length >
                1 && (
                <Panel
                    title="Income by release"
                    description="What each release has brought in to date."
                >
                    <SegmentedBar
                        format={(value) => <Amount value={value} />}
                        segments={[...releases]
                            .sort((a, b) => Number(b.income) - Number(a.income))
                            .map((release) => ({
                                label: release.name,
                                value: Number(release.income),
                            }))}
                    />
                </Panel>
            )}

            <IncomeReport report={report} />

            <MoversPanel
                movers={movers}
                to="release"
                title="Releases by income"
            />

            <Panel title="Releases" flush>
                {releases.length === 0 ? (
                    <div className="p-4">
                        <EmptyState title="No releases credited to this artist" />
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Release</TableHead>
                                <TableHead>Catalog number</TableHead>
                                <TableHead>Released</TableHead>
                                <TableHead className="text-right">
                                    Income
                                </TableHead>
                                <TableHead>
                                    <span className="sr-only">Play</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {releases.map((release) => (
                                <TableRow key={release.id}>
                                    <TableCell className="font-medium">
                                        <RecordLink
                                            to="release"
                                            id={release.id}
                                        >
                                            {release.name}
                                        </RecordLink>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {release.catalog_number ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {release.released_on
                                            ? date(release.released_on)
                                            : '–'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={release.income} />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <ReleaseActions
                                                tracks={release.player_tracks}
                                                name={release.name}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>

            <Panel title="Contracts and payees" flush>
                {contracts.length === 0 ? (
                    <div className="p-4">
                        <EmptyState title="No contract on this artist's releases or tracks" />
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Contract</TableHead>
                                <TableHead>Payees and their share</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {contracts.map((contract) => (
                                <TableRow key={contract.id}>
                                    <TableCell className="font-medium">
                                        <RecordLink
                                            to="contract"
                                            id={contract.id}
                                        >
                                            {contract.name}
                                        </RecordLink>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-x-4 gap-y-1">
                                            {contract.splits.map(
                                                (split, position) => (
                                                    <span key={position}>
                                                        <RecordLink
                                                            to="payee"
                                                            id={split.payee?.id}
                                                        >
                                                            {split.payee
                                                                ?.name ?? '–'}
                                                        </RecordLink>{' '}
                                                        <span className="text-muted-foreground tabular-nums">
                                                            {percent(
                                                                split.split_amount,
                                                            )}
                                                        </span>
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>

            <Panel title="Tracks" flush>
                {tracks.length === 0 ? (
                    <div className="p-4">
                        <EmptyState title="No tracks credited to this artist" />
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Track</TableHead>
                                <TableHead>Release</TableHead>
                                <TableHead>ISRC</TableHead>
                                <TableHead className="text-right">
                                    Airplay
                                </TableHead>
                                <TableHead className="text-right">
                                    Income
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tracks.map((track) => (
                                <TableRow key={track.id}>
                                    <TableCell className="font-medium">
                                        <RecordLink to="track" id={track.id}>
                                            {track.name}
                                        </RecordLink>
                                    </TableCell>
                                    <TableCell>
                                        {track.releases.length === 0 && '–'}
                                        {track.releases.map(
                                            (release, position) => (
                                                <span key={release.id}>
                                                    {position > 0 && ', '}
                                                    <RecordLink
                                                        to="release"
                                                        id={release.id}
                                                    >
                                                        {release.name}
                                                    </RecordLink>
                                                </span>
                                            ),
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {track.isrc ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count(track.airplays_count)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={track.income} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Panel>
        </Page>
    );
}
