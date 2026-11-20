import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Segment = {
    label: string;
    value: number;
    /** What is left over rather than a party of its own, drawn in grey. */
    remainder?: boolean;
};

/** Chart colours in their fixed order; a part keeps its colour by its position. */
const COLOURS = [
    'bg-chart-1',
    'bg-chart-2',
    'bg-chart-3',
    'bg-chart-4',
    'bg-chart-5',
    'bg-chart-6',
    'bg-chart-7',
    'bg-chart-8',
];

/** More parts than colours: the smallest are shown as one. */
const MOST = 7;

const GREY = 'bg-muted-foreground/40';

/**
 * The colour each part gets in the bar, in the order the parts are
 * given, for naming the parts elsewhere with the same colour. Null for
 * a part that is not drawn.
 */
export function partColours(segments: Segment[]): (string | null)[] {
    const parties = segments.filter(
        (segment) => segment.value > 0 && !segment.remainder,
    );

    return segments.map((segment) => {
        if (segment.value <= 0) {
            return null;
        }

        const position = parties.indexOf(segment);

        return segment.remainder ||
            (parties.length > MOST && position >= MOST - 1)
            ? GREY
            : COLOURS[position];
    });
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
}: {
    segments: Segment[];
    format: (value: number) => ReactNode;
    /** A thin bar without figures on it, for table rows. */
    compact?: boolean;
    className?: string;
}) {
    const positive = segments.filter((segment) => segment.value > 0);
    const parties = positive.filter((segment) => !segment.remainder);
    const shown =
        parties.length > MOST
            ? [
                  ...parties.slice(0, MOST - 1),
                  {
                      label: `${parties.length - MOST + 1} others`,
                      value: parties
                          .slice(MOST - 1)
                          .reduce((sum, segment) => sum + segment.value, 0),
                      remainder: true,
                  },
              ]
            : parties;
    const parts = [
        ...shown,
        ...positive.filter((segment) => segment.remainder),
    ].map((segment, position) => ({
        ...segment,
        colour: segment.remainder ? GREY : COLOURS[position % COLOURS.length],
    }));
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
                        key={part.label}
                        title={`${part.label}: ${share(part.value)}`}
                        className={cn(
                            'flex min-w-0.5 items-center justify-center overflow-hidden text-xs font-medium text-white',
                            part.colour,
                        )}
                        style={{ width: `${(part.value / total) * 100}%` }}
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
                            key={part.label}
                            className="flex items-baseline gap-1.5"
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'size-2.5 shrink-0 self-center rounded-sm',
                                    part.colour,
                                )}
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
