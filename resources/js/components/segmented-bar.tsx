import { useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Segment = {
    label: string;
    value: number;
    /** What is left over rather than a party of its own, drawn in grey. */
    remainder?: boolean;
};

/** How many chart colours the theme defines, in their fixed order. */
const THEMED = 8;

/** Grey belongs to the remainder alone, so no party is ever mistaken for it. */
export const GREY =
    'color-mix(in oklab, var(--muted-foreground) 45%, transparent)';

/**
 * The colour of the party at a position. The first eight are the theme's
 * chart colours; further ones step around the colour wheel by the golden
 * angle, so no two parties of one bar ever share a colour.
 */
export function colourAt(position: number): string {
    return position < THEMED
        ? `var(--chart-${position + 1})`
        : `oklch(${position % 2 === 0 ? 0.6 : 0.72} 0.14 ${Math.round((20 + (position - THEMED) * 137.508) % 360)})`;
}

/**
 * The colour each part gets in the bar, in the order the parts are
 * given, for naming the parts elsewhere with the same colour. Null for
 * a part that is not drawn.
 */
export function partColours(segments: Segment[]): (string | null)[] {
    let party = 0;

    return segments.map((segment) =>
        segment.value <= 0
            ? null
            : segment.remainder
              ? GREY
              : colourAt(party++),
    );
}

/**
 * One whole split into its parts: a bar with a block per part and a
 * legend that names each part with its figure. Parts keep the order they
 * are given in; parts that are zero or less are left out.
 */
export function SegmentedBar({
    segments,
    format,
    compact = false,
    className,
    active,
    onActive,
}: {
    /** The part pointed at, by its place in `segments`, when the page names the parts itself. */
    active?: number | null;
    onActive?: (position: number | null) => void;
    segments: Segment[];
    format: (value: number) => ReactNode;
    /** A thin bar without figures on it, for table rows. */
    compact?: boolean;
    className?: string;
}) {
    const colours = partColours(segments);
    const parts = segments.flatMap((segment, position) =>
        colours[position] === null
            ? []
            : [{ ...segment, position, colour: colours[position] as string }],
    );
    // Pointing at a part, in the bar or by its name, sets the others back.
    const [own, setOwn] = useState<number | null>(null);
    const pointed = active === undefined ? own : active;
    const point = onActive ?? setOwn;
    const dimmed = (position: number) =>
        pointed !== null && pointed !== position && 'opacity-25';
    const total = parts.reduce((sum, segment) => sum + segment.value, 0);

    if (total <= 0) {
        return null;
    }

    const share = (value: number) =>
        `${((value / total) * 100).toLocaleString('en-GB', { maximumFractionDigits: 1 })}%`;

    return (
        <div data-segmented-bar className={cn('grid gap-2', className)}>
            <div
                role="img"
                aria-label={parts
                    .map((part) => `${part.label} ${share(part.value)}`)
                    .join(', ')}
                className={cn(
                    'flex w-full gap-0.5 overflow-hidden rounded',
                    compact ? 'h-2.5' : 'h-9',
                )}
            >
                {parts.map((part) => (
                    <div
                        key={part.position}
                        data-part={part.position}
                        title={`${part.label}: ${share(part.value)}`}
                        onMouseEnter={() => point(part.position)}
                        onMouseLeave={() => point(null)}
                        className={cn(
                            'flex min-w-0.5 items-center justify-center overflow-hidden text-xs font-medium text-white transition-[width,opacity] duration-500 ease-out motion-reduce:transition-none',
                            dimmed(part.position),
                        )}
                        style={{
                            width: `${(part.value / total) * 100}%`,
                            backgroundColor: part.colour,
                        }}
                    >
                        {!compact &&
                            part.value / total >= 0.09 &&
                            share(part.value)}
                    </div>
                ))}
            </div>
            {!compact && (
                <ul className="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    {parts.map((part) => (
                        <li
                            key={part.position}
                            onMouseEnter={() => point(part.position)}
                            onMouseLeave={() => point(null)}
                            className={cn(
                                'flex items-baseline gap-1.5 transition-opacity',
                                dimmed(part.position),
                            )}
                        >
                            <span
                                aria-hidden
                                className="size-2.5 shrink-0 self-center rounded-sm"
                                style={{ backgroundColor: part.colour }}
                            />
                            <span>{part.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {format(part.value)} · {share(part.value)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
