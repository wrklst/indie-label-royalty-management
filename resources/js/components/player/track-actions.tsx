import { ListPlus, Pause, Play } from 'lucide-react';
import { toast } from 'sonner';
import {
    currentTrack,
    enqueue,
    playAll,
    playNow,
    toggle,
    usePlayer,
} from '@/components/player/player-store';
import type { PlayerTrack } from '@/components/player/player-store';
import { Button } from '@/components/ui/button';

/**
 * "Play now" and "Add to queue" for one track. When the track is the one in
 * the player, the first button pauses and resumes it instead.
 */
export function TrackActions({ track }: { track: PlayerTrack }) {
    const isCurrent = usePlayer(
        (state) => currentTrack(state)?.id === track.id,
    );
    // The queue remembers the file it was given; a master uploaded since replaces it.
    const isStale = usePlayer(
        (state) =>
            currentTrack(state)?.id === track.id &&
            currentTrack(state)?.fileId !== track.fileId,
    );
    const playing = usePlayer((state) => state.playing);
    const isPlaying = isCurrent && playing;

    return (
        <div className="flex items-center gap-1">
            <Button
                type="button"
                variant={isCurrent ? 'default' : 'outline'}
                size="icon"
                title={isPlaying ? 'Pause' : isCurrent ? 'Resume' : 'Play now'}
                aria-label={
                    isPlaying
                        ? `Pause ${track.title}`
                        : isCurrent
                          ? `Resume ${track.title}`
                          : `Play ${track.title}`
                }
                onClick={() =>
                    isCurrent && !isStale ? toggle() : playNow(track)
                }
            >
                {isPlaying ? <Pause /> : <Play />}
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                title="Add to queue"
                aria-label={`Add ${track.title} to queue`}
                onClick={() =>
                    enqueue([track]) === 1
                        ? toast.success(`${track.title} added to the queue.`)
                        : toast.info(`${track.title} is already in the queue.`)
                }
            >
                <ListPlus className="text-muted-foreground" />
            </Button>
            {isCurrent && (
                <span className="text-xs text-muted-foreground">
                    {isPlaying ? 'Playing' : 'Paused'}
                </span>
            )}
        </div>
    );
}

/**
 * "Play release" and "Queue release" for a list of tracks in play order.
 */
export function ReleaseActions({
    tracks,
    name,
}: {
    tracks: PlayerTrack[];
    /** Given in lists: the buttons shrink to icons and name the release for screen readers. */
    name?: string;
}) {
    if (tracks.length === 0) {
        return null;
    }

    const compact = name !== undefined;

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size={compact ? 'icon' : 'sm'}
                title={compact ? 'Play release' : undefined}
                aria-label={compact ? `Play release ${name}` : undefined}
                onClick={() => playAll(tracks)}
            >
                <Play />
                {!compact && 'Play release'}
            </Button>
            <Button
                type="button"
                variant={compact ? 'ghost' : 'outline'}
                size={compact ? 'icon' : 'sm'}
                title={compact ? 'Add release to queue' : undefined}
                aria-label={
                    compact ? `Add release ${name} to queue` : undefined
                }
                onClick={() => {
                    const added = enqueue(tracks);

                    if (added === 0) {
                        toast.info('These tracks are already in the queue.');
                    } else {
                        toast.success(
                            `${added} ${added === 1 ? 'track' : 'tracks'} added to the queue.`,
                        );
                    }
                }}
            >
                <ListPlus className={compact ? 'text-muted-foreground' : ''} />
                {!compact && 'Queue release'}
            </Button>
        </>
    );
}
