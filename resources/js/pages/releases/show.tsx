import { Link } from '@inertiajs/react';
import { Download, FileSpreadsheet, Pencil, Plus } from 'lucide-react';
import TrackController from '@/actions/App/Http/Controllers/TrackController';
import { Amount } from '@/components/amount';
import { ArtistCreditRows } from '@/components/artist-credit-rows';
import type { ArtistCredit } from '@/components/artist-credit-rows';
import { Field, SelectInput } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { Page, Panel, Stat } from '@/components/page';
import { ReleaseFiles } from '@/components/release-files';
import type { ReleaseFile } from '@/components/release-files';
import { TrackPlayer } from '@/components/track-player';
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
import { country, date, duration, quantity } from '@/lib/format';
import { edit as editContract } from '@/routes/contracts';
import { show as showFile } from '@/routes/release-files';
import { edit, index, metadata, packageMethod } from '@/routes/releases';
import {
    create as createTransaction,
    index as transactions,
} from '@/routes/transactions';
import type { Option } from '@/types';

type Credits = Partial<
    Record<
        'composers' | 'lyricists' | 'producers' | 'remixers' | 'publishers',
        string[]
    >
>;

type Track = {
    id: number;
    name: string;
    isrc: string | null;
    iswc: string | null;
    track_number: number | null;
    disc_number: number | null;
    duration: number | null;
    artist_id: number | null;
    contract_id: number | null;
    version: string | null;
    genre: string | null;
    language: string | null;
    explicit: string | null;
    bpm: string | null;
    musical_key: string | null;
    preview_start: number | null;
    p_line: string | null;
    lyrics: string | null;
    credits: Credits | null;
    artist: string | null;
    artists: ArtistCredit[];
    audio_file_id: number | null;
    contract: Option | null;
    income: string;
    quantity: string;
};

type Props = {
    release: {
        id: number;
        name: string;
        version: string | null;
        catalog_number: string | null;
        upc: string | null;
        released_on: string | null;
        original_released_on: string | null;
        preorder_on: string | null;
        status: 'todo' | 'in_progress' | 'done';
        release_type: string | null;
        artist: string | null;
        genre: string | null;
        subgenre: string | null;
        language: string | null;
        explicit: string | null;
        p_line: string | null;
        c_line: string | null;
        press_release: string | null;
        key_facts: string | null;
        todo: string | null;
        files_url: string | null;
        blocked_services: string[] | null;
        blocked_territories: string[] | null;
        notes: string | null;
        cover_file_id: number | null;
        contract: Option | null;
        files: ReleaseFile[];
        tracks: Track[];
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
    artistOptions: Option[];
    contractOptions: Option[];
    fileKinds: { value: string; label: string }[];
    maxUploadMegabytes: number;
};

const STATUS = {
    todo: { label: 'To do', variant: 'destructive' },
    in_progress: { label: 'In progress', variant: 'outline' },
    done: { label: 'Done', variant: 'secondary' },
} as const;

export function ReleaseStatusBadge({
    status,
}: {
    status: keyof typeof STATUS;
}) {
    return (
        <Badge variant={STATUS[status].variant}>{STATUS[status].label}</Badge>
    );
}

function TrackFields({
    track,
    artistOptions,
    contractOptions,
    errors,
}: {
    track: Track;
    artistOptions: Option[];
    contractOptions: Option[];
    errors: Record<string, string>;
}) {
    const credit = (role: keyof Credits) =>
        (track.credits?.[role] ?? []).join(', ');

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Title"
                    htmlFor="name"
                    error={errors.name}
                    required
                >
                    <Input
                        id="name"
                        name="name"
                        defaultValue={track.name}
                        required
                    />
                </Field>
                <Field
                    label="Version"
                    htmlFor="version"
                    error={errors.version}
                    hint="e.g. Radio Edit, Remix name"
                >
                    <Input
                        id="version"
                        name="version"
                        defaultValue={track.version ?? ''}
                    />
                </Field>
                <Field
                    label="Main artist"
                    htmlFor="artist_id"
                    error={errors.artist_id}
                >
                    <SelectInput
                        id="artist_id"
                        name="artist_id"
                        defaultValue={track.artist_id ?? ''}
                        options={artistOptions}
                        placeholder="Same as release"
                    />
                </Field>
                <Field
                    label="Contract"
                    htmlFor="contract_id"
                    error={errors.contract_id}
                >
                    <SelectInput
                        id="contract_id"
                        name="contract_id"
                        defaultValue={track.contract_id ?? ''}
                        options={contractOptions}
                        placeholder="Same as release"
                    />
                </Field>
            </div>
            <Field label="Further artist credits" error={errors.artists}>
                <ArtistCreditRows
                    artistOptions={artistOptions}
                    defaultValue={track.artists}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="ISRC" htmlFor="isrc" error={errors.isrc}>
                    <Input
                        id="isrc"
                        name="isrc"
                        defaultValue={track.isrc ?? ''}
                        className="font-mono"
                    />
                </Field>
                <Field label="ISWC" htmlFor="iswc" error={errors.iswc}>
                    <Input
                        id="iswc"
                        name="iswc"
                        defaultValue={track.iswc ?? ''}
                        className="font-mono"
                    />
                </Field>
                <Field
                    label="Duration (seconds)"
                    htmlFor="duration"
                    error={errors.duration}
                >
                    <Input
                        id="duration"
                        name="duration"
                        type="number"
                        min="0"
                        defaultValue={track.duration ?? ''}
                    />
                </Field>
                <Field
                    label="Disc"
                    htmlFor="disc_number"
                    error={errors.disc_number}
                >
                    <Input
                        id="disc_number"
                        name="disc_number"
                        type="number"
                        min="1"
                        defaultValue={track.disc_number ?? ''}
                    />
                </Field>
                <Field
                    label="Track number"
                    htmlFor="track_number"
                    error={errors.track_number}
                >
                    <Input
                        id="track_number"
                        name="track_number"
                        type="number"
                        min="1"
                        defaultValue={track.track_number ?? ''}
                    />
                </Field>
                <Field
                    label="Preview starts at (s)"
                    htmlFor="preview_start"
                    error={errors.preview_start}
                >
                    <Input
                        id="preview_start"
                        name="preview_start"
                        type="number"
                        min="0"
                        defaultValue={track.preview_start ?? ''}
                    />
                </Field>
                <Field label="BPM" htmlFor="bpm" error={errors.bpm}>
                    <Input
                        id="bpm"
                        name="bpm"
                        type="number"
                        step="0.01"
                        defaultValue={track.bpm ?? ''}
                    />
                </Field>
                <Field
                    label="Key"
                    htmlFor="musical_key"
                    error={errors.musical_key}
                >
                    <Input
                        id="musical_key"
                        name="musical_key"
                        defaultValue={track.musical_key ?? ''}
                        placeholder="F minor"
                    />
                </Field>
                <Field
                    label="Explicit"
                    htmlFor="explicit"
                    error={errors.explicit}
                >
                    <SelectInput
                        id="explicit"
                        name="explicit"
                        defaultValue={track.explicit ?? ''}
                        placeholder="Same as release"
                    >
                        <option value="explicit">Explicit</option>
                        <option value="clean">Clean</option>
                    </SelectInput>
                </Field>
                <Field label="Genre" htmlFor="genre" error={errors.genre}>
                    <Input
                        id="genre"
                        name="genre"
                        defaultValue={track.genre ?? ''}
                        placeholder="Same as release"
                    />
                </Field>
                <Field
                    label="Language"
                    htmlFor="language"
                    error={errors.language}
                    hint="e.g. en, de, zxx for instrumental"
                >
                    <Input
                        id="language"
                        name="language"
                        defaultValue={track.language ?? ''}
                        maxLength={8}
                    />
                </Field>
                <Field label="℗ line" htmlFor="p_line" error={errors.p_line}>
                    <Input
                        id="p_line"
                        name="p_line"
                        defaultValue={track.p_line ?? ''}
                        placeholder="Same as release"
                    />
                </Field>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Composers"
                    htmlFor="credits_composers"
                    hint="Separate names with commas."
                >
                    <Input
                        id="credits_composers"
                        name="credits[composers]"
                        defaultValue={credit('composers')}
                    />
                </Field>
                <Field label="Lyricists" htmlFor="credits_lyricists">
                    <Input
                        id="credits_lyricists"
                        name="credits[lyricists]"
                        defaultValue={credit('lyricists')}
                    />
                </Field>
                <Field label="Producers" htmlFor="credits_producers">
                    <Input
                        id="credits_producers"
                        name="credits[producers]"
                        defaultValue={credit('producers')}
                    />
                </Field>
                <Field label="Publishers" htmlFor="credits_publishers">
                    <Input
                        id="credits_publishers"
                        name="credits[publishers]"
                        defaultValue={credit('publishers')}
                    />
                </Field>
            </div>
            <Field label="Lyrics" htmlFor="lyrics" error={errors.lyrics}>
                <Textarea
                    id="lyrics"
                    name="lyrics"
                    defaultValue={track.lyrics ?? ''}
                />
            </Field>
        </>
    );
}

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{children || '–'}</dd>
        </div>
    );
}

export default function ShowRelease({
    release,
    totals,
    retailers,
    artistOptions,
    contractOptions,
    fileKinds,
    maxUploadMegabytes,
}: Props) {
    const title = release.version
        ? `${release.name} (${release.version})`
        : release.name;

    return (
        <Page
            title={title}
            description={
                <span className="flex flex-wrap items-center gap-2">
                    <ReleaseStatusBadge status={release.status} />
                    {[
                        release.artist,
                        release.release_type,
                        release.catalog_number,
                        release.upc && `UPC ${release.upc}`,
                        release.released_on &&
                            `released ${date(release.released_on)}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
            }
            actions={
                <>
                    <Button variant="outline" asChild>
                        <a href={packageMethod.url(release.id)}>
                            <Download />
                            Delivery package
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a
                            href={metadata.url({
                                query: { release: release.id },
                            })}
                        >
                            <FileSpreadsheet />
                            Metadata CSV
                        </a>
                    </Button>
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
            <div className="grid gap-4 lg:grid-cols-[13rem_1fr]">
                <div className="aspect-square w-52 overflow-hidden rounded-xl border bg-muted">
                    {release.cover_file_id ? (
                        <img
                            src={showFile.url(release.cover_file_id)}
                            alt={`Cover of ${release.name}`}
                            className="size-full object-cover"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center p-4 text-center text-xs text-muted-foreground">
                            No cover yet. Upload one under Files.
                        </div>
                    )}
                </div>
                <div className="grid content-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                                    Number(totals.income) +
                                    Number(totals.expense)
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
                                <TableHead>Length</TableHead>
                                <TableHead>Listen</TableHead>
                                <TableHead>Contract</TableHead>
                                <TableHead className="text-right">
                                    Units
                                </TableHead>
                                <TableHead className="text-right">
                                    Income
                                </TableHead>
                                <TableHead className="w-10" />
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
                                        {track.version && (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                ({track.version})
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>{track.artist ?? '–'}</TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {track.isrc ?? '–'}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {duration(track.duration)}
                                    </TableCell>
                                    <TableCell>
                                        {track.audio_file_id ? (
                                            <TrackPlayer
                                                src={showFile.url(
                                                    track.audio_file_id,
                                                )}
                                                title={track.name}
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                No audio
                                            </span>
                                        )}
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
                                    <TableCell>
                                        <FormDialog
                                            title={`Details of ${track.name}`}
                                            form={TrackController.update.form(
                                                track.id,
                                            )}
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Edit details of ${track.name}`}
                                                >
                                                    <Pencil className="text-muted-foreground" />
                                                </Button>
                                            }
                                        >
                                            {(errors) => (
                                                <TrackFields
                                                    track={track}
                                                    artistOptions={
                                                        artistOptions
                                                    }
                                                    contractOptions={
                                                        contractOptions
                                                    }
                                                    errors={errors}
                                                />
                                            )}
                                        </FormDialog>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {Number(totals.release_level_income) !== 0 && (
                                <TableRow>
                                    <TableCell />
                                    <TableCell
                                        colSpan={7}
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
                                    <TableCell />
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                )}
            </Panel>

            <Panel
                title="Files"
                description="Cover, audio masters, artwork and documents."
                flush
            >
                <ReleaseFiles
                    releaseId={release.id}
                    files={release.files}
                    tracks={release.tracks.map((track) => ({
                        id: track.id,
                        name: `${track.track_number ?? '–'}. ${track.name}`,
                    }))}
                    kinds={fileKinds}
                    maxMegabytes={maxUploadMegabytes}
                />
            </Panel>

            <Panel title="Release details">
                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Detail label="Format">{release.release_type}</Detail>
                    <Detail label="Genre">
                        {[release.genre, release.subgenre]
                            .filter(Boolean)
                            .join(' / ')}
                    </Detail>
                    <Detail label="Language">{release.language}</Detail>
                    <Detail label="Explicit">
                        {release.explicit &&
                            (release.explicit === 'explicit'
                                ? 'Explicit'
                                : 'Clean')}
                    </Detail>
                    <Detail label="℗ line">{release.p_line}</Detail>
                    <Detail label="© line">{release.c_line}</Detail>
                    <Detail label="Original release">
                        {release.original_released_on &&
                            date(release.original_released_on)}
                    </Detail>
                    <Detail label="Pre-order from">
                        {release.preorder_on && date(release.preorder_on)}
                    </Detail>
                    <Detail label="Not delivered to">
                        {release.blocked_services?.join(', ')}
                    </Detail>
                    <Detail label="Blocked territories">
                        {release.blocked_territories
                            ?.map((code) => country(code))
                            .join(', ')}
                    </Detail>
                    <Detail label="External files">
                        {release.files_url && (
                            <a
                                href={release.files_url}
                                target="_blank"
                                rel="noreferrer"
                                className="break-all underline underline-offset-4"
                            >
                                {release.files_url}
                            </a>
                        )}
                    </Detail>
                </dl>
            </Panel>

            {(release.todo || release.key_facts || release.press_release) && (
                <div className="grid gap-6 xl:grid-cols-2">
                    {release.todo && (
                        <Panel title="To do">
                            <p className="text-sm whitespace-pre-wrap">
                                {release.todo}
                            </p>
                        </Panel>
                    )}
                    {release.key_facts && (
                        <Panel title="Key facts">
                            <p className="text-sm whitespace-pre-wrap">
                                {release.key_facts}
                            </p>
                        </Panel>
                    )}
                    {release.press_release && (
                        <Panel title="Press release" className="xl:col-span-2">
                            <p className="max-w-3xl text-sm whitespace-pre-wrap">
                                {release.press_release}
                            </p>
                        </Panel>
                    )}
                </div>
            )}

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
