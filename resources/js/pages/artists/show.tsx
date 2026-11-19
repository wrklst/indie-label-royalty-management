import { EmptyState, Page, Panel } from '@/components/page';
import { RecordLink } from '@/components/record-link';
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
    releases: {
        id: number;
        name: string;
        catalog_number: string | null;
        released_on: string | null;
    }[];
    tracks: {
        id: number;
        name: string;
        isrc: string | null;
        release: { id: number; name: string } | null;
        airplays_count: number;
    }[];
};

export default function ShowArtist({ artist, releases, tracks }: Props) {
    return (
        <Page
            title={artist.name}
            description={`${releases.length} release(s), ${tracks.length} track(s)`}
        >
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
