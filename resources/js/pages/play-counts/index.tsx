import { Form } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import PlayCountController from '@/actions/App/Http/Controllers/PlayCountController';
import { DeleteButton } from '@/components/delete-button';
import { Field } from '@/components/field';
import { EmptyState, Page, Panel } from '@/components/page';
import { PlatformIcon } from '@/components/platform-icon';
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
import { date, quantity } from '@/lib/format';
import { index } from '@/routes/play-counts';

type Props = {
    files: {
        batch: string;
        source_file: string;
        platforms: { key: string; name: string }[];
        starts: string;
        ends: string;
        counts: number;
        matched: number;
        plays: number;
        created_at: string;
        unmatched: string[];
    }[];
};

/** Where each platform lets its figures be downloaded, as far as it does. */
const SOURCES = [
    'Spotify for Artists: Music, then Songs, then the download arrow above the table.',
    'Apple Music for Artists: Music, then See All; set the date range, then download the CSV.',
    'Amazon Music for Artists: set the date range on a report page and export it.',
    'Zebralution: the analytics export. It has every platform in one count and is listed as “Not named”.',
];

export default function PlayCounts({ files }: Props) {
    return (
        <Page
            title="Play counts"
            description="What the platforms themselves say was played, from the files their artist portals export. The reports hold these counts against the streams on your statements."
        >
            <Panel
                title="Add a file"
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

            {files.length === 0 ? (
                <EmptyState
                    title="No play counts yet"
                    description="Statements say what was paid for. A platform's own count shows whether that is all it played. Whole months compare best, since statements are monthly."
                />
            ) : (
                <Panel title="Files" flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>File</TableHead>
                                <TableHead>Platform</TableHead>
                                <TableHead>Covers</TableHead>
                                <TableHead className="text-right">
                                    Tracks matched
                                </TableHead>
                                <TableHead className="text-right">
                                    Plays
                                </TableHead>
                                <TableHead>Added</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {files.map((file) => (
                                <TableRow key={file.batch}>
                                    <TableCell>
                                        <div>{file.source_file}</div>
                                        {file.unmatched.length > 0 && (
                                            <div className="mt-1 max-w-md text-xs whitespace-normal text-muted-foreground">
                                                Not in the catalogue under that
                                                name:{' '}
                                                {file.unmatched.join(', ')}
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
                                        {file.matched} of {file.counts}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {quantity(file.plays)}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {date(file.created_at.slice(0, 10))}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <DeleteButton
                                            url={PlayCountController.destroy.url(
                                                file.batch,
                                            )}
                                            title="Delete these play counts?"
                                            description={`Every count from ${file.source_file} is removed. Statements and income are not touched.`}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>
            )}
        </Page>
    );
}

PlayCounts.layout = {
    breadcrumbs: [{ title: 'Play counts', href: index() }],
};
