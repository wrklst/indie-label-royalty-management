import { Form } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import PlayCountController from '@/actions/App/Http/Controllers/PlayCountController';
import { DeleteButton } from '@/components/delete-button';
import { Field } from '@/components/field';
import { MoversPanel, PlayReport } from '@/components/income-report';
import type { Movers, Report } from '@/components/income-report';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { PlatformIcon } from '@/components/platform-icon';
import { SortableHead } from '@/components/sortable-head';
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
import { count, date, quantity } from '@/lib/format';
import { index } from '@/routes/play-counts';
import type { Paginated } from '@/types';

type Props = {
    report: Report;
    tracks: Movers;
    releases: Movers;
    zebralutionExport: string | null;
    filters: { sort: string | null; direction: SortDirection | null };
    files: Paginated<{
        batch: string;
        source_file: string;
        platforms: { key: string; name: string }[];
        starts: string;
        ends: string;
        counts: number;
        matched: number;
        plays: number;
        created_at: string;
        unmatched: { names: string[]; total: number };
    }>;
};

/** Where each platform lets its figures be downloaded, as far as it does. */
const SOURCES = [
    'Spotify for Artists: Music, then Songs, then the download arrow above the table.',
    'Apple Music for Artists: Music, then See All; set the date range, then download the CSV.',
    'Amazon Music for Artists: set the date range on a report page and export it.',
];

export default function PlayCounts({
    files,
    filters,
    report,
    tracks,
    releases,
    zebralutionExport,
}: Props) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);

    return (
        <Page
            title="Play counts"
            description="What the platforms themselves say was played: how it developed, on which platform, and which releases and tracks it was. Held against the streams on your statements."
        >
            <Panel
                title="Import a file"
                description="The file as the portal exported it, CSV or Excel. The platform and the days are read from it; a file without dates counts as the total up to today. A newer file replaces earlier counts of the same track and platform for the days it covers."
            >
                <Form
                    {...PlayCountController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 md:max-w-md">
                                <Field
                                    label="File"
                                    htmlFor="play_count_file"
                                    error={errors.file}
                                    required
                                >
                                    <Input
                                        id="play_count_file"
                                        name="file"
                                        type="file"
                                        accept=".csv,.txt,.tsv,.xlsx"
                                        required
                                    />
                                </Field>
                            </div>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <ul className="grid gap-0.5 text-xs text-muted-foreground">
                                    {zebralutionExport && (
                                        <li>
                                            Zebralution:{' '}
                                            <a
                                                href={zebralutionExport}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="font-medium text-foreground underline underline-offset-4"
                                            >
                                                download the streams since the
                                                last import
                                            </a>
                                            , by track, platform and day. Sign
                                            in to{' '}
                                            <a
                                                href="https://zebralution.tools"
                                                target="_blank"
                                                rel="noreferrer"
                                                className="underline underline-offset-4"
                                            >
                                                Zebralution Tools
                                            </a>{' '}
                                            first.
                                        </li>
                                    )}
                                    {SOURCES.map((source) => (
                                        <li key={source}>{source}</li>
                                    ))}
                                </ul>
                                <Button type="submit" disabled={processing}>
                                    <Upload />
                                    Import play counts
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </Panel>

            <PlayReport report={report} />
            <MoversPanel
                movers={releases}
                to="release"
                title="Releases by plays"
                plays
            />
            <MoversPanel
                movers={tracks}
                to="track"
                title="Tracks by plays"
                plays
            />

            {files.total === 0 ? (
                <EmptyState
                    title="No play counts yet"
                    description="Statements say what was paid for. A platform's own count shows whether that is all it played. Whole months compare best, since statements are monthly."
                />
            ) : (
                <Panel title="Files" flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="file" sorter={sorter}>
                                    File
                                </SortableHead>
                                <SortableHead column="platform" sorter={sorter}>
                                    Platform
                                </SortableHead>
                                <SortableHead
                                    column="covers"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Covers
                                </SortableHead>
                                <SortableHead
                                    column="rows"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Rows
                                </SortableHead>
                                <SortableHead
                                    column="plays"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Plays
                                </SortableHead>
                                <SortableHead
                                    column="created_at"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Uploaded
                                </SortableHead>
                                <TableHead className="w-12" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {files.data.map((file) => (
                                <TableRow key={file.batch}>
                                    <TableCell>
                                        <div>{file.source_file}</div>
                                        {file.unmatched.total > 0 && (
                                            <div className="mt-1 max-w-md text-xs whitespace-normal text-muted-foreground">
                                                Not in the catalog under that
                                                name
                                                {file.unmatched.total >
                                                file.unmatched.names.length
                                                    ? ` (${count(file.unmatched.names.length)} of ${count(file.unmatched.total)})`
                                                    : ''}
                                                :{' '}
                                                {file.unmatched.names.join(
                                                    ', ',
                                                )}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="grid gap-1">
                                            {file.platforms.map((platform) => (
                                                <span
                                                    key={platform.key}
                                                    className="flex items-center gap-2"
                                                >
                                                    <PlatformIcon
                                                        platform={platform.key}
                                                        name={platform.name}
                                                    />
                                                    {platform.name}
                                                </span>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {date(file.starts)} – {date(file.ends)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count(file.counts)}
                                        <span className="block text-xs text-muted-foreground">
                                            {count(file.matched)} matched
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {quantity(file.plays)}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {date(file.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <DeleteButton
                                            url={PlayCountController.destroy.url(
                                                file.batch,
                                            )}
                                            title={`Delete ${file.source_file}?`}
                                            description={`The ${count(file.counts)} play counts read from it are removed. Statements and income are not touched.`}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={files} noun="files" />
                </Panel>
            )}
        </Page>
    );
}

PlayCounts.layout = {
    breadcrumbs: [{ title: 'Play counts', href: index() }],
};
