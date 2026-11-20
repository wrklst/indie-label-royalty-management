import { router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { useEffect, useState } from 'react';
import ReleaseFileController from '@/actions/App/Http/Controllers/ReleaseFileController';
import { DeleteButton } from '@/components/delete-button';
import { SelectInput } from '@/components/field';
import { FileDrop } from '@/components/file-drop';
import { TrackActions } from '@/components/player/track-actions';
import { SortableHead } from '@/components/sortable-head';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableSort } from '@/hooks/use-table-sort';
import { date } from '@/lib/format';
import { cn } from '@/lib/utils';
import { previews, show } from '@/routes/release-files';
import type { Option } from '@/types';

export type ReleaseFile = {
    id: number;
    original_name: string;
    mime_type: string | null;
    size: number;
    track_id: number | null;
    kind: string;
    track: string | null;
    inline: boolean;
    is_image: boolean;
    uploaded_at: string | null;
};

/** How far the MP3 an audio file is played from has come. */
type PlaybackCopy = {
    id: number;
    status: 'pending' | 'ready' | 'failed' | null;
    error: string | null;
    converted: boolean;
};

/**
 * The playback copies of a release's audio files, asked for again
 * whenever the files change and every few seconds while one is being made.
 */
function usePlaybackCopies(releaseId: number, audioFiles: string) {
    const [copies, setCopies] = useState<Record<number, PlaybackCopy>>({});
    const [round, setRound] = useState(0);

    useEffect(() => {
        if (audioFiles === '') {
            return;
        }

        let timer: number | undefined;
        const request = new AbortController();

        fetch(previews.url(releaseId), {
            headers: { Accept: 'application/json' },
            signal: request.signal,
        })
            .then((response) => (response.ok ? response.json() : []))
            .then((list: PlaybackCopy[]) => {
                setCopies(
                    Object.fromEntries(list.map((copy) => [copy.id, copy])),
                );

                if (list.some((copy) => copy.status === 'pending')) {
                    timer = window.setTimeout(
                        () => setRound((current) => current + 1),
                        5000,
                    );
                }
            })
            .catch(() => undefined);

        return () => {
            request.abort();
            window.clearTimeout(timer);
        };
    }, [releaseId, audioFiles, round]);

    return { copies, refresh: () => setRound((current) => current + 1) };
}

function PlaybackCopyNote({
    file,
    copy,
    onConvert,
}: {
    file: ReleaseFile;
    copy: PlaybackCopy | undefined;
    onConvert: () => void;
}) {
    if (file.kind !== 'audio' || copy === undefined) {
        return null;
    }

    if (copy.status === 'ready') {
        return (
            <span className="block text-xs text-muted-foreground">
                {copy.converted
                    ? 'MP3 playback copy ready'
                    : 'Played as uploaded'}
            </span>
        );
    }

    if (copy.status === 'pending') {
        return (
            <span className="block text-xs text-muted-foreground">
                Converting for playback…
            </span>
        );
    }

    return (
        <span className="flex items-center gap-2 text-xs">
            {copy.status === 'failed' ? (
                <span
                    title={copy.error ?? undefined}
                    className="text-red-600 underline decoration-dotted underline-offset-2 dark:text-red-400"
                >
                    Playback copy failed
                </span>
            ) : (
                <span className="text-muted-foreground">
                    No playback copy yet
                </span>
            )}
            <button
                type="button"
                onClick={onConvert}
                className="underline decoration-muted-foreground/40 underline-offset-2"
            >
                {copy.status === 'failed' ? 'Convert again' : 'Convert'}
            </button>
        </span>
    );
}

function fileSize(bytes: number): string {
    if (bytes >= 1024 * 1024 * 1024) {
        return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
    }

    if (bytes >= 1024 * 1024) {
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * The private files of a release: upload, say what each one is, attach
 * audio to tracks, download and delete.
 */
const FILE_COLUMNS = {
    original_name: (row: ReleaseFile) => row.original_name,
    kind: (row: ReleaseFile) => row.kind,
    track: (row: ReleaseFile) => row.track,
    size: (row: ReleaseFile) => row.size,
    uploaded_at: (row: ReleaseFile) => row.uploaded_at,
};

export function ReleaseFiles({
    releaseId,
    files,
    tracks,
    kinds,
    maxMegabytes,
    player,
}: {
    /** What the player shows next to an audio file played from here. */
    player: {
        release: { id: number; name: string };
        artist: string | null;
        artistId: number | null;
        coverFileId: number | null;
    };
    releaseId: number;
    files: ReleaseFile[];
    tracks: Option[];
    kinds: { value: string; label: string }[];
    maxMegabytes: number;
}) {
    const { rows: sortedFiles, sorter } = useTableSort(files, FILE_COLUMNS);
    const [kind, setKind] = useState('');
    const [tab, setTab] = useState('');
    const tabs = [
        { value: '', label: 'All', count: files.length },
        ...kinds
            .map((option) => ({
                ...option,
                count: files.filter((file) => file.kind === option.value)
                    .length,
            }))
            .filter((option) => option.count > 0),
    ];
    // A type whose last file was deleted or retyped has no tab any more.
    const shown = tabs.some((option) => option.value === tab) ? tab : '';
    const fileRows = sortedFiles.filter(
        (file) => shown === '' || file.kind === shown,
    );
    const [errors, setErrors] = useState<string[]>([]);
    const { copies, refresh } = usePlaybackCopies(
        releaseId,
        files
            .filter((file) => file.kind === 'audio')
            .map((file) => file.id)
            .join(','),
    );

    const convert = (file: ReleaseFile) =>
        router.post(
            ReleaseFileController.preview.url(file.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: refresh,
            },
        );

    const change = (file: ReleaseFile, changes: Partial<ReleaseFile>) =>
        router.patch(
            ReleaseFileController.update.url(file.id),
            {
                kind: changes.kind ?? file.kind,
                track_id:
                    changes.track_id !== undefined
                        ? changes.track_id
                        : file.track_id,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (response) => setErrors(Object.values(response)),
            },
        );

    return (
        <div>
            <div className="grid gap-3 border-b px-4 py-3">
                <div data-release-upload>
                    <FileDrop
                        url={ReleaseFileController.store.url(releaseId)}
                        data={{ kind: kind || null }}
                        maxMegabytes={maxMegabytes}
                        label="Drop the cover, masters and other files here"
                    />
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <SelectInput
                        aria-label="Upload as"
                        value={kind}
                        onChange={(event) => setKind(event.target.value)}
                        className="w-44"
                    >
                        <option value="">Detect type</option>
                        {kinds.map((option) => (
                            <option key={option.value} value={option.value}>
                                As {option.label.toLowerCase()}
                            </option>
                        ))}
                    </SelectInput>
                    <span className="text-xs text-muted-foreground">
                        Up to {maxMegabytes} MB per file. Files are private:
                        only signed-in admins of this label can open them.
                    </span>
                </div>
            </div>

            {errors.length > 0 && (
                <ul
                    role="alert"
                    className="grid gap-1 border-b px-4 py-3 text-sm text-red-600 dark:text-red-400"
                >
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}

            {files.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">
                    No files yet. Upload the cover, the audio masters and
                    anything else that belongs to the release. Audio named like
                    the track, or starting with its number, is attached to that
                    track.
                </p>
            ) : (
                <>
                    <div
                        role="tablist"
                        aria-label="File types"
                        className="flex flex-wrap gap-1 border-b px-4 py-2"
                    >
                        {tabs.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="tab"
                                aria-selected={shown === option.value}
                                onClick={() => setTab(option.value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm transition-colors',
                                    shown === option.value
                                        ? 'bg-muted font-medium'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                )}
                            >
                                {option.label}{' '}
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {option.count}
                                </span>
                            </button>
                        ))}
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    column="original_name"
                                    sorter={sorter}
                                >
                                    File
                                </SortableHead>
                                <SortableHead column="kind" sorter={sorter}>
                                    Type
                                </SortableHead>
                                <SortableHead column="track" sorter={sorter}>
                                    Track
                                </SortableHead>
                                <SortableHead
                                    column="size"
                                    sorter={sorter}
                                    first="desc"
                                    className="text-right"
                                >
                                    Size
                                </SortableHead>
                                <SortableHead
                                    column="uploaded_at"
                                    sorter={sorter}
                                    first="desc"
                                >
                                    Uploaded
                                </SortableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {fileRows.map((file) => (
                                <TableRow key={file.id}>
                                    <TableCell className="max-w-md">
                                        <div className="flex items-center gap-3">
                                            <div className="flex w-20 shrink-0 justify-start">
                                                {file.kind === 'audio' ? (
                                                    <TrackActions
                                                        track={{
                                                            // A file on no track is its own entry in the queue.
                                                            id:
                                                                file.track_id ??
                                                                -file.id,
                                                            fileId: file.id,
                                                            title:
                                                                file.track ??
                                                                file.original_name,
                                                            artist: player.artist,
                                                            artistId:
                                                                player.artistId,
                                                            release:
                                                                player.release,
                                                            coverFileId:
                                                                player.coverFileId,
                                                        }}
                                                        name={
                                                            file.original_name
                                                        }
                                                        bare
                                                    />
                                                ) : file.is_image ? (
                                                    <a
                                                        href={show.url(file.id)}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        title="Open the original"
                                                    >
                                                        <img
                                                            src={show.url(
                                                                file.id,
                                                                {
                                                                    query: {
                                                                        size: 'thumb',
                                                                    },
                                                                },
                                                            )}
                                                            alt=""
                                                            width={40}
                                                            height={40}
                                                            loading="lazy"
                                                            decoding="async"
                                                            className="size-10 rounded-md border bg-muted object-cover"
                                                        />
                                                    </a>
                                                ) : (
                                                    <span className="flex size-10 items-center justify-center rounded-md border bg-muted">
                                                        <FileText className="size-4 text-muted-foreground" />
                                                    </span>
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                {file.inline ? (
                                                    <a
                                                        href={show.url(file.id)}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="block truncate font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                                    >
                                                        {file.original_name}
                                                    </a>
                                                ) : (
                                                    <span className="block truncate font-medium">
                                                        {file.original_name}
                                                    </span>
                                                )}
                                                <PlaybackCopyNote
                                                    file={file}
                                                    copy={copies[file.id]}
                                                    onConvert={() =>
                                                        convert(file)
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <SelectInput
                                            aria-label={`Type of ${file.original_name}`}
                                            value={file.kind}
                                            onChange={(event) =>
                                                change(file, {
                                                    kind: event.target.value,
                                                })
                                            }
                                            options={undefined}
                                            className="w-32"
                                        >
                                            {kinds.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </SelectInput>
                                    </TableCell>
                                    <TableCell>
                                        {file.kind === 'audio' ? (
                                            <SelectInput
                                                aria-label={`Track for ${file.original_name}`}
                                                value={file.track_id ?? ''}
                                                onChange={(event) =>
                                                    change(file, {
                                                        track_id: event.target
                                                            .value
                                                            ? Number(
                                                                  event.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    })
                                                }
                                                options={tracks}
                                                placeholder="No track"
                                                className="w-56"
                                            />
                                        ) : (
                                            <span className="text-muted-foreground">
                                                –
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">
                                        {fileSize(file.size)}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {date(file.uploaded_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <a
                                                    href={show.url(file.id, {
                                                        query: { download: 1 },
                                                    })}
                                                    aria-label={`Download ${file.original_name}`}
                                                >
                                                    <Download className="text-muted-foreground" />
                                                </a>
                                            </Button>
                                            <DeleteButton
                                                url={ReleaseFileController.destroy.url(
                                                    file.id,
                                                )}
                                                title={`Delete ${file.original_name}?`}
                                                description="The file is removed from the server for good. Download it first if you have no other copy."
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </>
            )}
        </div>
    );
}
