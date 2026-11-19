import { Amount } from '@/components/amount';
import { EmptyState, Page, Panel, Stat } from '@/components/page';
import type { PlayerTrack } from '@/components/player/player-store';
import { ReleaseActions } from '@/components/player/track-actions';
import { RecordLink } from '@/components/record-link';
import { SegmentedBar } from '@/components/segmented-bar';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { date } from '@/lib/format';
import { index } from '@/routes/artists';

type Props = {
    artist: { id: number; name: string };
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
    tracks: {
        id: number;
        name: string;
        isrc: string | null;
        release: { id: number; name: string } | null;
        airplays_count: number;
        income: string;
    }[];
};

export default function ShowArtist({
    artist,
    totals,
    releases,
    tracks,
}: Props) {
    return (
        <Page
            title={artist.name}
            description={`${releases.length} release(s), ${tracks.length} track(s)`}
        >
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="Income to date"
                    value={<Amount value={totals.income} />}
                    hint={`${Number(totals.units).toLocaleString('en-GB', { maximumFractionDigits: 0 })} units in ${totals.transactions.toLocaleString('en-GB')} transactions`}
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
                    value={totals.airplays.toLocaleString('en-GB')}
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
                                        <RecordLink
                                            to="release"
                                            id={track.release?.id}
                                        >
                                            {track.release?.name ?? '–'}
                                        </RecordLink>
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {track.isrc ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {track.airplays_count}
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

ShowArtist.layout = {
    breadcrumbs: [
        { title: 'Artists', href: index() },
        { title: 'Artist', href: '#' },
    ],
};
