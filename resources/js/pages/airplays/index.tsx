import { Form, router, useHttp } from '@inertiajs/react';
import {
    Check,
    ExternalLink,
    Link2,
    Pencil,
    Plus,
    Radar,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import AirplayController from '@/actions/App/Http/Controllers/AirplayController';
import AirplayResearchController from '@/actions/App/Http/Controllers/AirplayResearchController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { Field, SelectInput } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { EmptyState, Page, Panel } from '@/components/page';
import { SortableHead } from '@/components/sortable-head';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useTableSort } from '@/hooks/use-table-sort';
import { country, date } from '@/lib/format';
import { index } from '@/routes/airplays';
import type { Option } from '@/types';

type Origin = 'statement' | 'search' | 'manual';

/** How the label learned of a play, or of a link kept with it. */
const ORIGINS: Record<Origin, string> = {
    statement: 'Neighbouring rights statement',
    search: 'Web search',
    manual: 'Entered by hand',
};

type AirplayLink = {
    url: string;
    title: string | null;
    description: string | null;
    source?: Origin;
};

type Finding = {
    id: number;
    is_radio: boolean;
    station: string | null;
    url: string;
    title: string;
    description: string | null;
    domain: string | null;
    query: string;
    airplay_id: number | null;
    track: string | null;
};

type Airplay = {
    id: number;
    origin: Origin;
    track_id: number | null;
    track_name: string;
    artist_name: string | null;
    isrc: string | null;
    station: string;
    media_type: string | null;
    region: string | null;
    society: string | null;
    amount: string | null;
    notes: string | null;
    data_import_id: number | null;
    period_start: string | null;
    period_end: string | null;
    links: AirplayLink[];
    search_query: string;
};

type SearchResult = {
    title: string;
    url: string;
    description: string | null;
    domain: string | null;
};

function period(airplay: Airplay): string {
    if (!airplay.period_start) {
        return '–';
    }

    return airplay.period_end && airplay.period_end !== airplay.period_start
        ? `${date(airplay.period_start)} – ${date(airplay.period_end)}`
        : date(airplay.period_start);
}

function AirplayFields({
    airplay,
    tracks,
    errors,
}: {
    airplay?: Airplay;
    tracks: Option[];
    errors: Record<string, string>;
}) {
    return (
        <>
            <Field
                label="Track"
                htmlFor="track_id"
                error={errors.track_id}
                hint="Pick a track from the catalog, or type the names below."
            >
                <SelectInput
                    id="track_id"
                    name="track_id"
                    options={tracks}
                    placeholder="Not in the catalog"
                    defaultValue={airplay?.track_id ?? ''}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Track name"
                    htmlFor="track_name"
                    error={errors.track_name}
                >
                    <Input
                        id="track_name"
                        name="track_name"
                        defaultValue={airplay?.track_name}
                    />
                </Field>
                <Field
                    label="Artist"
                    htmlFor="artist_name"
                    error={errors.artist_name}
                >
                    <Input
                        id="artist_name"
                        name="artist_name"
                        defaultValue={airplay?.artist_name ?? ''}
                    />
                </Field>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Station"
                    htmlFor="station"
                    error={errors.station}
                    required
                >
                    <Input
                        id="station"
                        name="station"
                        defaultValue={airplay?.station}
                        placeholder="FluxFM"
                        required
                    />
                </Field>
                <Field
                    label="Country"
                    htmlFor="region"
                    error={errors.region}
                    hint="Two-letter code, e.g. DE."
                >
                    <Input
                        id="region"
                        name="region"
                        maxLength={2}
                        defaultValue={airplay?.region ?? ''}
                    />
                </Field>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Played on / from"
                    htmlFor="period_start"
                    error={errors.period_start}
                >
                    <Input
                        id="period_start"
                        name="period_start"
                        type="date"
                        defaultValue={airplay?.period_start ?? ''}
                    />
                </Field>
                <Field
                    label="Until"
                    htmlFor="period_end"
                    error={errors.period_end}
                    hint="Leave empty for a single day."
                >
                    <Input
                        id="period_end"
                        name="period_end"
                        type="date"
                        defaultValue={airplay?.period_end ?? ''}
                    />
                </Field>
            </div>
            <Field label="Notes" htmlFor="notes" error={errors.notes}>
                <Textarea
                    id="notes"
                    name="notes"
                    defaultValue={airplay?.notes ?? ''}
                    placeholder="Show, presenter, how you found out…"
                />
            </Field>
        </>
    );
}

/**
 * The links kept for a play, and a web search to find more.
 */
function LinksDialog({
    airplay,
    searchAvailable,
}: {
    airplay: Airplay;
    searchAvailable: boolean;
}) {
    const [open, setOpen] = useState(false);
    const search = useHttp<{ query: string }, { results: SearchResult[] }>({
        query: airplay.search_query,
    });
    const [results, setResults] = useState<SearchResult[] | null>(null);
    const saved = new Set(airplay.links.map((link) => link.url));

    const save = (link: AirplayLink) =>
        router.post(AirplayController.storeLink.url(airplay.id), link, {
            preserveScroll: true,
            preserveState: true,
        });

    const runSearch = async (event: React.FormEvent) => {
        event.preventDefault();

        try {
            const response = await search.post(
                AirplayController.search.url(airplay.id),
            );
            setResults(response.results);
        } catch {
            // The reason is in search.errors.query.
            setResults(null);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`Links for ${airplay.track_name} on ${airplay.station}`}
                >
                    <Link2 className="text-muted-foreground" />
                    {airplay.links.length}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {airplay.track_name} on {airplay.station}
                    </DialogTitle>
                    <DialogDescription>
                        Keep playlists, show pages and anything else you found
                        about this play.
                    </DialogDescription>
                </DialogHeader>

                {airplay.links.length > 0 && (
                    <ul className="grid gap-2">
                        {airplay.links.map((link) => (
                            <li
                                key={link.url}
                                className="flex items-start gap-2 rounded-md border p-2"
                            >
                                <div className="min-w-0 flex-1">
                                    <a
                                        href={link.url}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="flex items-center gap-1 text-sm font-medium hover:underline"
                                    >
                                        <span className="truncate">
                                            {link.title ?? link.url}
                                        </span>
                                        <ExternalLink className="size-3 shrink-0" />
                                    </a>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {link.source === 'search'
                                            ? 'Found by web search'
                                            : 'Added by hand'}
                                        {' · '}
                                        {link.url}
                                    </p>
                                    {link.description && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {link.description}
                                        </p>
                                    )}
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove ${link.title ?? link.url}`}
                                    onClick={() =>
                                        router.delete(
                                            AirplayController.destroyLink.url(
                                                airplay.id,
                                            ),
                                            {
                                                data: { url: link.url },
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    }
                                >
                                    <X className="text-muted-foreground" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                <Form
                    {...AirplayController.storeLink.form(airplay.id)}
                    options={{ preserveScroll: true, preserveState: true }}
                    resetOnSuccess
                    className="grid gap-2 sm:grid-cols-[2fr_1fr_auto]"
                >
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <Input
                                    name="url"
                                    type="url"
                                    placeholder="https://…"
                                    aria-label="Link"
                                    required
                                />
                                <InputError message={errors.url} />
                            </div>
                            <Input
                                name="title"
                                placeholder="Title (optional)"
                                aria-label="Title"
                            />
                            <Button variant="secondary" disabled={processing}>
                                <Plus />
                                Add link
                            </Button>
                        </>
                    )}
                </Form>

                <div className="grid gap-2 border-t pt-4">
                    <h3 className="text-sm font-medium">Search the web</h3>
                    <p className="text-xs text-muted-foreground">
                        A search takes about half a minute.
                    </p>
                    {searchAvailable ? (
                        <form onSubmit={runSearch} className="flex gap-2">
                            <Input
                                value={search.data.query}
                                onChange={(event) =>
                                    search.setData('query', event.target.value)
                                }
                                aria-label="Search words"
                            />
                            <Button disabled={search.processing}>
                                <Search />
                                {search.processing ? 'Searching…' : 'Search'}
                            </Button>
                        </form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Web search is not set up. Add DATAFORSEO_LOGIN and
                            DATAFORSEO_PASSWORD to the environment.
                        </p>
                    )}
                    <InputError message={search.errors.query} />
                    {results?.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nothing found. Try fewer or other words.
                        </p>
                    )}
                    {results && results.length > 0 && (
                        <ul className="grid gap-2">
                            {results.map((result) => (
                                <li
                                    key={result.url}
                                    className="flex items-start gap-2 rounded-md border p-2"
                                >
                                    <div className="min-w-0 flex-1">
                                        <a
                                            href={result.url}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            className="flex items-center gap-1 text-sm font-medium hover:underline"
                                        >
                                            <span className="truncate">
                                                {result.title}
                                            </span>
                                            <ExternalLink className="size-3 shrink-0" />
                                        </a>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {result.domain ?? result.url}
                                        </p>
                                        {result.description && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {result.description}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={saved.has(result.url)}
                                        onClick={() =>
                                            save({
                                                url: result.url,
                                                title: result.title,
                                                description: result.description,
                                                source: 'search',
                                            })
                                        }
                                    >
                                        {saved.has(result.url)
                                            ? 'Saved'
                                            : 'Save'}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Start the automatic research for a group of tracks.
 */
function ResearchDialog({
    tracks,
    releases,
    staleCount,
    staleDays,
}: {
    tracks: Option[];
    releases: Option[];
    staleCount: number;
    staleDays: number;
}) {
    const [scope, setScope] = useState('stale');

    return (
        <FormDialog
            title="Research airplay"
            description="Each track is searched on the web: once in general and once for every station a statement named. Pages that may show a play are listed as findings for you to keep or dismiss. Every search is billed by DataForSEO and takes about half a minute, so a large group runs in the background for a while."
            form={AirplayResearchController.store.form()}
            submitLabel="Start research"
            trigger={
                <Button variant="outline">
                    <Radar />
                    Research
                </Button>
            }
        >
            {(errors) => (
                <>
                    <Field label="Tracks" htmlFor="scope" error={errors.scope}>
                        <SelectInput
                            id="scope"
                            name="scope"
                            value={scope}
                            onChange={(event) => setScope(event.target.value)}
                        >
                            <option value="stale">
                                Not researched in the last {staleDays} days (
                                {staleCount})
                            </option>
                            <option value="release">One release</option>
                            <option value="tracks">Selected tracks</option>
                            <option value="all">
                                Every track ({tracks.length})
                            </option>
                        </SelectInput>
                    </Field>
                    {scope === 'release' && (
                        <Field
                            label="Release"
                            htmlFor="release_id"
                            error={errors.release_id}
                            required
                        >
                            <SelectInput
                                id="release_id"
                                name="release_id"
                                options={releases}
                                required
                            />
                        </Field>
                    )}
                    {scope === 'tracks' && (
                        <Field
                            label="Tracks"
                            htmlFor="track_ids"
                            error={errors.track_ids}
                            hint="Hold Cmd or Ctrl to pick several."
                            required
                        >
                            <select
                                id="track_ids"
                                name="track_ids[]"
                                multiple
                                required
                                size={10}
                                className="w-full rounded-md border border-input bg-transparent p-1 text-sm"
                            >
                                {tracks.map((track) => (
                                    <option key={track.id} value={track.id}>
                                        {track.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                </>
            )}
        </FormDialog>
    );
}

/**
 * Pages the research found, waiting to be kept or dismissed.
 */
function Findings({ findings }: { findings: Finding[] }) {
    const decide = (url: string) =>
        router.post(url, {}, { preserveScroll: true, preserveState: true });

    return (
        <Panel
            title={`Findings to review (${findings.length})`}
            description="Found by the automatic research. Keep a page to store it with the play, or as a new play when the station was not known yet. A dismissed page is not suggested again."
            flush
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Track</TableHead>
                        <TableHead>Page</TableHead>
                        <TableHead>Why</TableHead>
                        <TableHead className="w-44" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {findings.map((finding) => (
                        <TableRow key={finding.id}>
                            <TableCell className="align-top font-medium">
                                {finding.track ?? '–'}
                            </TableCell>
                            <TableCell className="max-w-md align-top whitespace-normal">
                                <a
                                    href={finding.url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="flex items-center gap-1 font-medium hover:underline"
                                >
                                    <span className="truncate">
                                        {finding.title}
                                    </span>
                                    <ExternalLink className="size-3 shrink-0" />
                                </a>
                                <p className="text-xs text-muted-foreground">
                                    {finding.domain}
                                </p>
                                {finding.description && (
                                    <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                        {finding.description}
                                    </p>
                                )}
                            </TableCell>
                            <TableCell className="align-top whitespace-normal">
                                {finding.station ? (
                                    <Badge>{finding.station}</Badge>
                                ) : finding.is_radio ? (
                                    <Badge variant="secondary">
                                        Looks like radio
                                    </Badge>
                                ) : (
                                    <Badge variant="outline">Mention</Badge>
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Searched: {finding.query}
                                </p>
                            </TableCell>
                            <TableCell className="align-top">
                                <div className="flex justify-end gap-1">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            decide(
                                                AirplayResearchController.keep.url(
                                                    finding.id,
                                                ),
                                            )
                                        }
                                    >
                                        <Check />
                                        Keep
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            decide(
                                                AirplayResearchController.dismiss.url(
                                                    finding.id,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                        Dismiss
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </Panel>
    );
}

const SORT_COLUMNS = {
    track: (row: Airplay) => row.track_name,
    artist: (row: Airplay) => row.artist_name,
    station: (row: Airplay) => row.station,
    origin: (row: Airplay) => ORIGINS[row.origin],
    period: (row: Airplay) => row.period_start,
    amount: (row: Airplay) => (row.amount === null ? null : Number(row.amount)),
    links: (row: Airplay) => row.links.length,
};

export default function Airplays({
    airplays,
    findings,
    tracks,
    releases,
    staleCount,
    staleDays,
    searchAvailable,
}: {
    airplays: Airplay[];
    findings: Finding[];
    tracks: Option[];
    releases: Option[];
    staleCount: number;
    staleDays: number;
    searchAvailable: boolean;
}) {
    const { rows, sorter } = useTableSort(airplays, SORT_COLUMNS);

    return (
        <Page
            title="Airplay"
            description="Where your tracks were played, and how you know: from a neighbouring rights statement, from the web research, or entered by hand. Keep links to playlists and show pages with each play."
            actions={
                <>
                    {searchAvailable && (
                        <ResearchDialog
                            tracks={tracks}
                            releases={releases}
                            staleCount={staleCount}
                            staleDays={staleDays}
                        />
                    )}
                    <FormDialog
                        title="Add airplay"
                        form={AirplayController.store.form()}
                        submitLabel="Add airplay"
                        trigger={
                            <Button>
                                <Plus />
                                Add airplay
                            </Button>
                        }
                    >
                        {(errors) => (
                            <AirplayFields tracks={tracks} errors={errors} />
                        )}
                    </FormDialog>
                </>
            }
        >
            {findings.length > 0 && <Findings findings={findings} />}
            {airplays.length === 0 ? (
                <EmptyState
                    title="No airplay yet"
                    description="Import a Rightz Audio statement under Data imports, start a research, or add a play by hand."
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="track" sorter={sorter}>
                                    Track
                                </SortableHead>
                                <SortableHead column="artist" sorter={sorter}>
                                    Artist
                                </SortableHead>
                                <SortableHead column="station" sorter={sorter}>
                                    Station
                                </SortableHead>
                                <SortableHead column="origin" sorter={sorter}>
                                    Known from
                                </SortableHead>
                                <SortableHead column="period" sorter={sorter}>
                                    Played
                                </SortableHead>
                                <SortableHead
                                    column="amount"
                                    sorter={sorter}
                                    className="text-right"
                                >
                                    Earned
                                </SortableHead>
                                <SortableHead column="links" sorter={sorter}>
                                    Links
                                </SortableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((airplay) => (
                                <TableRow key={airplay.id}>
                                    <TableCell className="font-medium">
                                        {airplay.track_name}
                                        {airplay.notes && (
                                            <p className="max-w-md truncate text-xs font-normal text-muted-foreground">
                                                {airplay.notes}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {airplay.artist_name ?? '–'}
                                    </TableCell>
                                    <TableCell>
                                        {airplay.station}
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                airplay.media_type,
                                                airplay.region &&
                                                    country(airplay.region),
                                                airplay.society,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                airplay.origin === 'statement'
                                                    ? 'default'
                                                    : airplay.origin ===
                                                        'search'
                                                      ? 'secondary'
                                                      : 'outline'
                                            }
                                        >
                                            {ORIGINS[airplay.origin]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {period(airplay)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {airplay.amount === null ? (
                                            '–'
                                        ) : (
                                            <Amount value={airplay.amount} />
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <LinksDialog
                                            airplay={airplay}
                                            searchAvailable={searchAvailable}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <FormDialog
                                                title="Edit airplay"
                                                form={AirplayController.update.form(
                                                    airplay.id,
                                                )}
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${airplay.track_name} on ${airplay.station}`}
                                                    >
                                                        <Pencil className="text-muted-foreground" />
                                                    </Button>
                                                }
                                            >
                                                {(errors) => (
                                                    <AirplayFields
                                                        airplay={airplay}
                                                        tracks={tracks}
                                                        errors={errors}
                                                    />
                                                )}
                                            </FormDialog>
                                            <DeleteButton
                                                url={AirplayController.destroy.url(
                                                    airplay.id,
                                                )}
                                                title={`Delete ${airplay.track_name} on ${airplay.station}?`}
                                                description="The play and its links and notes are removed. Income from the statement stays."
                                            />
                                        </div>
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

Airplays.layout = { breadcrumbs: [{ title: 'Airplay', href: index() }] };
