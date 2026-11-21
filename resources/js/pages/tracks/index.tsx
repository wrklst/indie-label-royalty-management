import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import TrackController from '@/actions/App/Http/Controllers/TrackController';
import { FilterBar } from '@/components/filter-bar';
import { DeleteButton } from '@/components/delete-button';
import { Field, SelectInput } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { TrackActions } from '@/components/player/track-actions';
import { RecordLink, recordLinkClass } from '@/components/record-link';
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
import { show as showRelease } from '@/routes/releases';
import { index } from '@/routes/tracks';
import type { Option, Paginated } from '@/types';

type Track = {
    id: number;
    name: string;
    artist_id: number | null;
    contract_id: number | null;
    isrc: string | null;
    iswc: string | null;
    track_number: number | null;
    duration: number | null;
    version: string | null;
    artist: string | null;
    credited_artist: string | null;
    audio_file_id: number | null;
    cover_file_id: number | null;
    release: { id: number; name: string; catalog_number: string | null } | null;
    releases: { id: number; name: string }[];
    without_contract_on: string[];
    contract: Option | null;
    inherited_contract: Option | null;
};

type Props = {
    tracks: Paginated<Track>;
    filters: {
        search: string;
        release: number | null;
        sort: string | null;
        direction: SortDirection | null;
    };
    artistOptions: Option[];
    contractOptions: Option[];
    releaseOptions: Option[];
};

export default function Tracks({
    tracks,
    filters,
    artistOptions,
    contractOptions,
    releaseOptions,
}: Props) {
    const { apply } = useFilters(index.url(), filters);
    const sorter = serverSorter(filters, apply);
    const filtered = filters.search !== '' || filters.release !== null;

    return (
        <Page
            title="Tracks"
            description="Every recording across all releases. A recording on several releases, such as a single and an album, is listed once with all of them; pick a release to see and edit its own entry."
        >
            <FilterBar
                url={index.url()}
                filters={filters}
                search="Search title or ISRC…"
                selects={[
                    {
                        name: 'release',
                        label: 'Release',
                        placeholder: 'All releases',
                        options: releaseOptions,
                        className: 'w-full sm:w-72',
                    },
                ]}
            />

            {tracks.total === 0 ? (
                <EmptyState
                    title={filtered ? 'No track matches' : 'No tracks yet'}
                    description={
                        filtered
                            ? undefined
                            : 'Tracks are added with their release or created from your imports.'
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Title
                                </SortableHead>
                                <SortableHead column="artist" sorter={sorter}>
                                    Artist
                                </SortableHead>
                                <SortableHead column="release" sorter={sorter}>
                                    Release
                                </SortableHead>
                                <SortableHead column="isrc" sorter={sorter}>
                                    ISRC
                                </SortableHead>
                                <SortableHead column="contract" sorter={sorter}>
                                    Contract
                                </SortableHead>
                                <TableHead>Listen</TableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tracks.data.map((track) => (
                                <TableRow key={track.id}>
                                    <TableCell className="font-medium">
                                        <RecordLink to="track" id={track.id}>
                                            {track.name}
                                        </RecordLink>
                                    </TableCell>
                                    <TableCell>
                                        <RecordLink
                                            to="artist"
                                            id={track.artist_id}
                                        >
                                            {track.artist ?? '–'}
                                        </RecordLink>
                                    </TableCell>
                                    <TableCell>
                                        {track.releases.map(
                                            (release, position) => (
                                                <span key={release.id}>
                                                    {position > 0 && ', '}
                                                    <Link
                                                        href={showRelease(
                                                            release.id,
                                                        )}
                                                        className={
                                                            recordLinkClass
                                                        }
                                                    >
                                                        {release.name}
                                                    </Link>
                                                </span>
                                            ),
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {track.isrc ?? '–'}
                                    </TableCell>
                                    <TableCell>
                                        {track.contract ? (
                                            track.contract.name
                                        ) : track.inherited_contract ? (
                                            <span className="text-muted-foreground">
                                                {track.inherited_contract.name}{' '}
                                                (release)
                                            </span>
                                        ) : (
                                            <Badge variant="warning">
                                                No contract
                                            </Badge>
                                        )}
                                        {track.without_contract_on.map(
                                            (release) => (
                                                <Badge
                                                    key={release}
                                                    variant="warning"
                                                    className="ml-2"
                                                >
                                                    No contract on {release}
                                                </Badge>
                                            ),
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {track.audio_file_id ? (
                                            <TrackActions
                                                track={{
                                                    id: track.id,
                                                    fileId: track.audio_file_id,
                                                    title: track.version
                                                        ? `${track.name} (${track.version})`
                                                        : track.name,
                                                    artist: track.credited_artist,
                                                    artistId: track.artist_id,
                                                    release: track.release && {
                                                        id: track.release.id,
                                                        name: track.release
                                                            .name,
                                                    },
                                                    coverFileId:
                                                        track.cover_file_id,
                                                }}
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                No audio
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <FormDialog
                                                title={`Edit ${track.name}`}
                                                form={TrackController.update.form(
                                                    track.id,
                                                )}
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${track.name}`}
                                                    >
                                                        <Pencil className="text-muted-foreground" />
                                                    </Button>
                                                }
                                            >
                                                {(errors) => (
                                                    <>
                                                        <Field
                                                            label="Title"
                                                            htmlFor="name"
                                                            error={errors.name}
                                                            required
                                                        >
                                                            <Input
                                                                id="name"
                                                                name="name"
                                                                defaultValue={
                                                                    track.name
                                                                }
                                                                required
                                                            />
                                                        </Field>
                                                        <Field
                                                            label="Artist"
                                                            htmlFor="artist_id"
                                                            error={
                                                                errors.artist_id
                                                            }
                                                        >
                                                            <SelectInput
                                                                id="artist_id"
                                                                name="artist_id"
                                                                defaultValue={
                                                                    track.artist_id ??
                                                                    ''
                                                                }
                                                                options={
                                                                    artistOptions
                                                                }
                                                                placeholder="No artist"
                                                            />
                                                        </Field>
                                                        <Field
                                                            label="Contract"
                                                            htmlFor="contract_id"
                                                            error={
                                                                errors.contract_id
                                                            }
                                                            hint="Leave empty to use the contract of the release."
                                                        >
                                                            <SelectInput
                                                                id="contract_id"
                                                                name="contract_id"
                                                                defaultValue={
                                                                    track.contract_id ??
                                                                    ''
                                                                }
                                                                options={
                                                                    contractOptions
                                                                }
                                                                placeholder={
                                                                    track.inherited_contract
                                                                        ? `Same as release (${track.inherited_contract.name})`
                                                                        : 'Same as release'
                                                                }
                                                            />
                                                        </Field>
                                                        <div className="grid gap-4 sm:grid-cols-2">
                                                            <Field
                                                                label="ISRC"
                                                                htmlFor="isrc"
                                                                error={
                                                                    errors.isrc
                                                                }
                                                            >
                                                                <Input
                                                                    id="isrc"
                                                                    name="isrc"
                                                                    defaultValue={
                                                                        track.isrc ??
                                                                        ''
                                                                    }
                                                                    className="font-mono"
                                                                />
                                                            </Field>
                                                            <Field
                                                                label="ISWC"
                                                                htmlFor="iswc"
                                                                error={
                                                                    errors.iswc
                                                                }
                                                            >
                                                                <Input
                                                                    id="iswc"
                                                                    name="iswc"
                                                                    defaultValue={
                                                                        track.iswc ??
                                                                        ''
                                                                    }
                                                                    className="font-mono"
                                                                />
                                                            </Field>
                                                            <Field
                                                                label="Track number"
                                                                htmlFor="track_number"
                                                                error={
                                                                    errors.track_number
                                                                }
                                                            >
                                                                <Input
                                                                    id="track_number"
                                                                    name="track_number"
                                                                    type="number"
                                                                    min="1"
                                                                    defaultValue={
                                                                        track.track_number ??
                                                                        ''
                                                                    }
                                                                />
                                                            </Field>
                                                            <Field
                                                                label="Length (seconds)"
                                                                htmlFor="duration"
                                                                error={
                                                                    errors.duration
                                                                }
                                                            >
                                                                <Input
                                                                    id="duration"
                                                                    name="duration"
                                                                    type="number"
                                                                    min="0"
                                                                    defaultValue={
                                                                        track.duration ??
                                                                        ''
                                                                    }
                                                                />
                                                            </Field>
                                                        </div>
                                                    </>
                                                )}
                                            </FormDialog>
                                            <DeleteButton
                                                url={TrackController.destroy.url(
                                                    track.id,
                                                )}
                                                title={`Delete ${track.name}?`}
                                                description="Its transactions stay on the release but lose the track and any track-level contract."
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={tracks} noun="tracks" />
                </Panel>
            )}
        </Page>
    );
}

Tracks.layout = { breadcrumbs: [{ title: 'Tracks', href: index() }] };
