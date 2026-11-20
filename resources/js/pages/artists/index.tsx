import { RecordLink } from '@/components/record-link';
import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import ArtistController from '@/actions/App/Http/Controllers/ArtistController';
import { DeleteButton } from '@/components/delete-button';
import { Field, SelectInput } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
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
import { Textarea } from '@/components/ui/textarea';
import { useFilters } from '@/hooks/use-filters';
import { serverSorter } from '@/hooks/use-table-sort';
import type { SortDirection } from '@/hooks/use-table-sort';
import { index } from '@/routes/artists';
import { index as releases } from '@/routes/releases';
import type { Option, Paginated } from '@/types';

type Artist = {
    id: number;
    name: string;
    alias_of_id: number | null;
    alias_of: string | null;
    spotify_id: string | null;
    discogs_search_name: string | null;
    notes: string | null;
    releases_count: number;
    tracks_count: number;
    aliases_count: number;
    aliases: string[];
};

function ArtistFields({
    artist,
    options,
    errors,
}: {
    artist?: Artist;
    options: Option[];
    errors: Record<string, string>;
}) {
    return (
        <>
            <Field label="Name" htmlFor="name" error={errors.name} required>
                <Input
                    id="name"
                    name="name"
                    defaultValue={artist?.name}
                    required
                />
            </Field>
            <Field
                label="Alias of"
                htmlFor="alias_of_id"
                error={errors.alias_of_id}
                hint="Set this when the artist is another name of an artist already in the catalog."
            >
                <SelectInput
                    id="alias_of_id"
                    name="alias_of_id"
                    defaultValue={artist?.alias_of_id ?? ''}
                    placeholder="Not an alias"
                    options={options.filter(
                        (option) => option.id !== artist?.id,
                    )}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Spotify ID"
                    htmlFor="spotify_id"
                    error={errors.spotify_id}
                >
                    <Input
                        id="spotify_id"
                        name="spotify_id"
                        defaultValue={artist?.spotify_id ?? ''}
                    />
                </Field>
                <Field
                    label="Discogs search name"
                    htmlFor="discogs_search_name"
                    error={errors.discogs_search_name}
                >
                    <Input
                        id="discogs_search_name"
                        name="discogs_search_name"
                        defaultValue={artist?.discogs_search_name ?? ''}
                    />
                </Field>
            </div>
            <Field label="Notes" htmlFor="notes" error={errors.notes}>
                <Textarea
                    id="notes"
                    name="notes"
                    defaultValue={artist?.notes ?? ''}
                />
            </Field>
        </>
    );
}

export default function Artists({
    artists,
    filters,
    artistOptions,
}: {
    artists: Paginated<Artist>;
    filters: {
        search: string;
        aliases: boolean;
        sort: string | null;
        direction: SortDirection | null;
    };
    artistOptions: Option[];
}) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);

    return (
        <Page
            title="Artists"
            description="The performers on your releases and tracks. Artists are created automatically when an import mentions a new name. Other spellings of an artist are its aliases and are listed with it."
            actions={
                <FormDialog
                    title="New artist"
                    form={ArtistController.store.form()}
                    submitLabel="Create artist"
                    trigger={
                        <Button>
                            <Plus />
                            New artist
                        </Button>
                    }
                >
                    {(errors) => (
                        <ArtistFields options={artistOptions} errors={errors} />
                    )}
                </FormDialog>
            }
        >
            <Input
                type="search"
                placeholder="Search artists…"
                defaultValue={filters.search}
                onChange={(event) =>
                    apply({ search: event.target.value }, true)
                }
                className="max-w-sm"
            />
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    className="size-4 accent-primary"
                    checked={filters.aliases}
                    onChange={(event) =>
                        apply({ aliases: event.target.checked })
                    }
                />
                Show aliases as rows of their own
            </label>

            {artists.total === 0 ? (
                <EmptyState
                    title={
                        filters.search
                            ? 'No artist matches your search'
                            : 'No artists yet'
                    }
                    description={
                        filters.search
                            ? undefined
                            : 'Import a statement and the artists in it are added for you, or create one by hand.'
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Name
                                </SortableHead>
                                <SortableHead
                                    column="releases_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Releases
                                </SortableHead>
                                <SortableHead
                                    column="tracks_count"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Tracks
                                </SortableHead>
                                <SortableHead column="notes" sorter={sorter}>
                                    Notes
                                </SortableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {artists.data.map((artist) => (
                                <TableRow key={artist.id}>
                                    <TableCell>
                                        <span className="font-medium">
                                            <RecordLink
                                                to="artist"
                                                id={artist.id}
                                            >
                                                {artist.name}
                                            </RecordLink>
                                        </span>
                                        {artist.alias_of && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                alias of {artist.alias_of}
                                            </Badge>
                                        )}
                                        {artist.aliases.length > 0 && (
                                            <span className="block text-xs text-muted-foreground">
                                                also{' '}
                                                {artist.aliases.join(' · ')}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {artist.releases_count > 0 ? (
                                            <Link
                                                href={releases({
                                                    query: {
                                                        artist: artist.id,
                                                    },
                                                })}
                                                className="underline decoration-muted-foreground/40 underline-offset-4"
                                            >
                                                {artist.releases_count}
                                            </Link>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                0
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {artist.tracks_count}
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate text-muted-foreground">
                                        {artist.notes}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <FormDialog
                                                title={`Edit ${artist.name}`}
                                                form={ArtistController.update.form(
                                                    artist.id,
                                                )}
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${artist.name}`}
                                                    >
                                                        <Pencil className="text-muted-foreground" />
                                                    </Button>
                                                }
                                            >
                                                {(errors) => (
                                                    <ArtistFields
                                                        artist={artist}
                                                        options={artistOptions}
                                                        errors={errors}
                                                    />
                                                )}
                                            </FormDialog>
                                            <DeleteButton
                                                url={ArtistController.destroy.url(
                                                    artist.id,
                                                )}
                                                title={`Delete ${artist.name}?`}
                                                description="Releases and tracks of this artist stay in the catalog but lose their artist."
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={artists} noun="artists" />
                </Panel>
            )}
        </Page>
    );
}

Artists.layout = { breadcrumbs: [{ title: 'Artists', href: index() }] };
