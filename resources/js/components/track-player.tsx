import { Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { duration as formatDuration } from '@/lib/format';

const PLAY_EVENT = 'track-player:play';

/**
 * Plays one audio file from the protected file route. Starting a player
 * stops any other one on the page.
 */
export function TrackPlayer({ src, title }: { src: string; title: string }) {
    const audio = useRef<HTMLAudioElement | null>(null);
    const [playing, setPlaying] = useState(false);
    const [position, setPosition] = useState(0);
    const [length, setLength] = useState(0);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const stopOthers = (event: Event) => {
            if ((event as CustomEvent).detail !== audio.current) {
                audio.current?.pause();
            }
        };

        window.addEventListener(PLAY_EVENT, stopOthers);

        return () => window.removeEventListener(PLAY_EVENT, stopOthers);
    }, []);

    const toggle = () => {
        const element = audio.current;

        if (!element) {
            return;
        }

        if (element.paused) {
            window.dispatchEvent(
                new CustomEvent(PLAY_EVENT, { detail: element }),
            );
            element.play().catch(() => setFailed(true));
        } else {
            element.pause();
        }
    };

    if (failed) {
        return (
            <span className="text-xs text-muted-foreground">
                This format does not play in the browser
            </span>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <audio
                ref={audio}
                src={src}
                preload="none"
                onPlay={() => setPlaying(true)}
                onPause={() => setPlaying(false)}
                onEnded={() => setPlaying(false)}
                onError={() => setFailed(true)}
                onTimeUpdate={(event) =>
                    setPosition(event.currentTarget.currentTime)
                }
                onLoadedMetadata={(event) =>
                    setLength(
                        Number.isFinite(event.currentTarget.duration)
                            ? event.currentTarget.duration
                            : 0,
                    )
                }
            />
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={playing ? `Pause ${title}` : `Play ${title}`}
                onClick={toggle}
            >
                {playing ? <Pause /> : <Play />}
            </Button>
            <input
                type="range"
                aria-label={`Position in ${title}`}
                className="h-1 w-28 accent-foreground"
                min={0}
                max={length || 1}
                step={0.1}
                value={position}
                disabled={length === 0}
                onChange={(event) => {
                    if (audio.current) {
                        audio.current.currentTime = Number(event.target.value);
                    }
                }}
            />
            <span className="w-10 text-xs text-muted-foreground tabular-nums">
                {position > 0 || playing
                    ? formatDuration(Math.floor(position)) === '–'
                        ? '0:00'
                        : formatDuration(Math.floor(position))
                    : ''}
            </span>
        </div>
    );
}
