import { router } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ReleaseFileController from '@/actions/App/Http/Controllers/ReleaseFileController';
import { DeleteButton } from '@/components/delete-button';
import { SelectInput } from '@/components/field';
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
}: {
    releaseId: number;
    files: ReleaseFile[];
    tracks: Option[];
    kinds: { value: string; label: string }[];
    maxMegabytes: number;
}) {
    const { rows: fileRows, sorter } = useTableSort(files, FILE_COLUMNS);
    const input = useRef<HTMLInputElement | null>(null);
    const [kind, setKind] = useState('');
    const [queue, setQueue] = useState<{ name: string; progress: number }[]>(
        [],
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

    // One file per request keeps every upload under the server's size limit.
    const upload = async (selected: FileList | null) => {
        const chosen = Array.from(selected ?? []);
        const problems: string[] = [];

        setErrors([]);
        setQueue(chosen.map((file) => ({ name: file.name, progress: 0 })));

        for (const [position, file] of chosen.entries()) {
            if (file.size > maxMegabytes * 1024 * 1024) {
                problems.push(
                    `${file.name} is larger than ${maxMegabytes} MB.`,
                );
                continue;
            }

            await new Promise<void>((resolve) => {
                router.post(
                    ReleaseFileController.store.url(releaseId),
                    { file, kind: kind || null },
                    {
                        forceFormData: true,
                        preserveScroll: true,
                        preserveState: true,
                        onProgress: (event) =>
                            setQueue((current) =>
                                current.map((entry, index) =>
                                    index === position
                                        ? {
                                              ...entry,
                                              progress: event?.percentage ?? 0,
                                          }
                                        : entry,
                                ),
                            ),
                        onError: (response) =>
                            problems.push(
                                `${file.name}: ${Object.values(response).join(' ')}`,
                            ),
                        onFinish: () => resolve(),
                    },
                );
            });
        }

        setQueue([]);
        setErrors(problems);

        if (input.current) {
            input.current.value = '';
        }
    };

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
            <div className="flex flex-wrap items-center gap-3 border-b px-4 py-3">
                <input
                    ref={input}
                    id="release-file-input"
                    type="file"
                    multiple
                    className="sr-only"
                    onChange={(event) => upload(event.target.files)}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={queue.length > 0}
                    onClick={() => input.current?.click()}
                >
                    <Upload />
                    {queue.length > 0 ? 'Uploading…' : 'Upload files'}
                </Button>
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
                    Up to {maxMegabytes} MB per file. Files are private: only
                    signed-in admins of this label can open them.
                </span>
            </div>

            {queue.length > 0 && (
                <ul className="grid gap-1 border-b px-4 py-3 text-sm">
                    {queue.map((entry) => (
                        <li
                            key={entry.name}
                            className="flex items-center gap-3"
                        >
                            <span className="min-w-0 flex-1 truncate">
                                {entry.name}
                            </span>
                            <span className="h-1 w-32 overflow-hidden rounded-full bg-muted">
                                <span
                                    className="block h-full bg-foreground/70"
                                    style={{ width: `${entry.progress}%` }}
                                />
                            </span>
                        </li>
                    ))}
                </ul>
            )}

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
                                <TableCell className="max-w-xs">
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
                                        onConvert={() => convert(file)}
                                    />
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
                                                    track_id: event.target.value
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
            )}
        </div>
    );
}
