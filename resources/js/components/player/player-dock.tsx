import { Link, usePage } from '@inertiajs/react';
import {
    ListMusic,
    Music,
    Pause,
    Play,
    SkipBack,
    SkipForward,
    Trash2,
    Volume2,
    VolumeX,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';
import {
    clear,
    currentTrack,
    jumpTo,
    next,
    previous,
    remove,
    seek,
    setVolume,
    syncScopeFromPage,
    toggle,
    toggleMuted,
    usePlayer,
} from '@/components/player/player-store';
import type { PlayerTrack } from '@/components/player/player-store';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show as showFile } from '@/routes/release-files';
import { show as showRelease } from '@/routes/releases';

function clock(seconds: number): string {
    const whole = Math.max(0, Math.floor(seconds));

    return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
}

function Cover({
    track,
    className,
}: {
    track: PlayerTrack;
    className: string;
}) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted text-muted-foreground',
                className,
            )}
        >
            {track.coverFileId ? (
                <img
                    src={showFile.url(track.coverFileId, {
                        query: { size: 'thumb' },
                    })}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <Music className="size-4" aria-hidden />
            )}
        </div>
    );
}

const rangeClass =
    'cursor-pointer appearance-none rounded-full disabled:cursor-default [&::-moz-range-thumb]:size-3 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-foreground [&::-webkit-slider-thumb]:size-3 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-foreground';

/**
 * Native range inputs draw their track differently in every browser, so
 * the filled part is painted as a background instead.
 */
function rangeFill(fraction: number): CSSProperties {
    const percent = Math.round(Math.min(1, Math.max(0, fraction)) * 1000) / 10;

    return {
        background: `linear-gradient(to right, var(--foreground) ${percent}%, var(--border) ${percent}%)`,
    };
}

function SeekBar() {
    const position = usePlayer((state) => state.position);
    const duration = usePlayer((state) => state.duration);

    return (
        <div className="order-last flex w-full items-center gap-2 text-xs text-muted-foreground tabular-nums md:order-none md:w-auto md:flex-1">
            <span className="w-9 text-right" data-player="elapsed">
                {clock(position)}
            </span>
            <input
                type="range"
                aria-label="Seek"
                aria-valuetext={`${clock(position)} of ${clock(duration)}`}
                className={`h-1 min-w-0 flex-1 ${rangeClass}`}
                style={rangeFill(duration > 0 ? position / duration : 0)}
                min={0}
                max={duration || 1}
                step={0.1}
                value={Math.min(position, duration || 1)}
                disabled={duration === 0}
                onChange={(event) => seek(Number(event.target.value))}
            />
            <span className="w-9" data-player="total">
                {duration > 0 ? clock(duration) : '–:––'}
            </span>
        </div>
    );
}

function Volume() {
    const volume = usePlayer((state) => state.volume);
    const muted = usePlayer((state) => state.muted);
    const silent = muted || volume === 0;

    return (
        <div className="hidden items-center gap-1 md:flex">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={muted ? 'Unmute' : 'Mute'}
                aria-pressed={muted}
                onClick={toggleMuted}
            >
                {silent ? <VolumeX /> : <Volume2 />}
            </Button>
            <input
                type="range"
                aria-label="Volume"
                className={`h-1 w-20 ${rangeClass}`}
                style={rangeFill(muted ? 0 : volume)}
                min={0}
                max={1}
                step={0.05}
                value={muted ? 0 : volume}
                onChange={(event) => setVolume(Number(event.target.value))}
            />
        </div>
    );
}

function Queue({ onClose }: { onClose: () => void }) {
    const queue = usePlayer((state) => state.queue);
    const index = usePlayer((state) => state.index);
    const playing = usePlayer((state) => state.playing);
    const failed = usePlayer((state) => state.failed);

    return (
        <section
            id="player-queue"
            aria-label="Queue"
            className="absolute right-0 bottom-full z-10 flex max-h-[min(28rem,70svh)] w-full flex-col border bg-popover text-popover-foreground shadow-lg sm:right-2 sm:mb-2 sm:w-96 sm:rounded-xl"
        >
            <div className="flex items-center justify-between gap-2 border-b px-3 py-2">
                <h2 className="text-sm font-semibold">
                    Queue{' '}
                    <span className="font-normal text-muted-foreground">
                        {queue.length} {queue.length === 1 ? 'track' : 'tracks'}
                    </span>
                </h2>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={clear}
                    >
                        <Trash2 />
                        Clear queue
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Close queue"
                        onClick={onClose}
                    >
                        <X />
                    </Button>
                </div>
            </div>
            <ol className="overflow-y-auto p-1">
                {queue.map((track, position) => {
                    const current = position === index;

                    return (
                        <li
                            key={track.id}
                            data-current={current || undefined}
                            className={cn(
                                'flex items-center gap-1 rounded-md',
                                current && 'bg-accent text-accent-foreground',
                            )}
                        >
                            <button
                                type="button"
                                aria-label={`Play ${track.title} from the queue`}
                                aria-current={current ? 'true' : undefined}
                                onClick={() => jumpTo(position)}
                                className="flex min-w-0 flex-1 items-center gap-3 rounded-md px-2 py-1.5 text-left outline-none hover:bg-accent/60 focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Cover track={track} className="size-8" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">
                                        {track.title}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {failed.includes(track.id)
                                            ? 'Could not be played'
                                            : [
                                                  track.artist,
                                                  track.release?.name,
                                              ]
                                                  .filter(Boolean)
                                                  .join(' · ')}
                                    </span>
                                </span>
                                {current && (
                                    <span className="text-xs text-muted-foreground">
                                        {playing ? 'Playing' : 'Paused'}
                                    </span>
                                )}
                            </button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Remove ${track.title} from the queue`}
                                onClick={() => remove(position)}
                            >
                                <X className="text-muted-foreground" />
                            </Button>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

/**
 * Space starts and stops playback unless the key belongs to whatever has
 * the focus: a form control, a button, a link or editable text.
 */
function useSpaceToToggle(enabled: boolean): void {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.code !== 'Space' ||
                event.defaultPrevented ||
                event.repeat ||
                event.altKey ||
                event.ctrlKey ||
                event.metaKey ||
                event.shiftKey
            ) {
                return;
            }

            const target = event.target;

            if (
                target instanceof HTMLElement &&
                (target.isContentEditable ||
                    target.closest(
                        'input, textarea, select, button, a[href], summary, [role="button"], [role="menuitem"], [role="option"], [role="dialog"], [role="slider"], [role="tab"], [role="switch"], [role="checkbox"], [role="combobox"]',
                    ) !== null)
            ) {
                return;
            }

            event.preventDefault();
            toggle();
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [enabled]);
}

/**
 * The player bar at the bottom of the app. It is part of the page flow
 * (sticky, not fixed), so it never covers the end of a page, and it is
 * not rendered at all while nothing is queued.
 */
export function PlayerDock() {
    const { auth, label } = usePage().props;
    const userId = auth?.user?.id ?? null;
    const labelId = label?.id ?? null;
    const role = label?.role ?? null;
    const track = usePlayer(currentTrack);
    const playing = usePlayer((state) => state.playing);
    const error = usePlayer((state) => state.error);
    const index = usePlayer((state) => state.index);
    const length = usePlayer((state) => state.queue.length);
    const [queueOpen, setQueueOpen] = useState(false);

    useEffect(() => {
        syncScopeFromPage(
            userId,
            labelId === null ? null : { id: labelId, role },
        );
    }, [userId, labelId, role]);

    useSpaceToToggle(track !== null);

    useEffect(() => {
        if (!queueOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setQueueOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [queueOpen]);

    if (!track) {
        return null;
    }

    return (
        <aside
            aria-label="Audio player"
            data-player="dock"
            className="sticky bottom-0 z-30 mt-auto border-t bg-background"
        >
            {queueOpen && <Queue onClose={() => setQueueOpen(false)} />}
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2">
                <div className="flex min-w-0 flex-1 items-center gap-3 md:w-64 md:flex-none lg:w-80">
                    <Cover track={track} className="size-10" />
                    <div className="min-w-0">
                        <div
                            className="truncate text-sm font-medium"
                            data-player="title"
                        >
                            {track.title}
                        </div>
                        {error ? (
                            <div
                                role="alert"
                                className="truncate text-xs text-red-600 dark:text-red-400"
                                title={error}
                            >
                                {error}
                            </div>
                        ) : (
                            <div className="truncate text-xs text-muted-foreground">
                                {track.artist}
                                {track.artist && track.release && ' · '}
                                {track.release && (
                                    <Link
                                        href={showRelease(track.release.id)}
                                        className="underline decoration-muted-foreground/40 underline-offset-2 hover:text-foreground"
                                    >
                                        {track.release.name}
                                    </Link>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Previous track"
                        className="hidden sm:inline-flex"
                        onClick={previous}
                    >
                        <SkipBack />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        aria-label={playing ? 'Pause' : 'Play'}
                        className="rounded-full"
                        onClick={toggle}
                    >
                        {playing ? <Pause /> : <Play />}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Next track"
                        disabled={index >= length - 1}
                        onClick={next}
                    >
                        <SkipForward />
                    </Button>
                </div>

                <SeekBar />

                <div className="flex items-center gap-1">
                    <Volume />
                    <Button
                        type="button"
                        variant={queueOpen ? 'secondary' : 'ghost'}
                        size="sm"
                        aria-label={queueOpen ? 'Close queue' : 'Open queue'}
                        aria-expanded={queueOpen}
                        aria-controls="player-queue"
                        onClick={() => setQueueOpen((open) => !open)}
                    >
                        <ListMusic />
                        <span className="tabular-nums" data-player="count">
                            {length}
                        </span>
                    </Button>
                </div>
            </div>
        </aside>
    );
}
