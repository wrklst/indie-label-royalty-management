import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Amount } from '@/components/amount';
import { Panel } from '@/components/page';
import { PlatformIcon } from '@/components/platform-icon';
import { RecordLink } from '@/components/record-link';
import { GREY, SegmentedBar, colourAt } from '@/components/segmented-bar';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    compact,
    country,
    date,
    decimal,
    money,
    month,
    quantity,
} from '@/lib/format';
import { cn } from '@/lib/utils';

export type Report = {
    months: string[];
    total: number;
    platforms: {
        key: string;
        name: string;
        position: number;
        total: number;
        streams: number;
        stream_total: number;
        downloads: number;
        download_total: number;
        other_units: number;
        plays: number | null;
        plays_from: string | null;
        plays_to: string | null;
        reported_streams: number | null;
        series: { total: number[]; streams: number[]; stream_total: number[] };
    }[];
    kinds: { key: string; label: string; total: number; units: number }[];
    regions: { label: string | null; total: number; units: number }[];
};

export type Movers = {
    months: string[];
    rows: {
        id: number;
        label: string;
        total: number;
        streams: number;
        stream_total: number;
        recent: number;
        before: number;
        series: number[];
    }[];
};

type Platform = Report['platforms'][number];
type Metric = 'total' | 'streams' | 'rate';

const METRICS: { key: Metric; label: string }[] = [
    { key: 'total', label: 'Income' },
    { key: 'streams', label: 'Streams' },
    { key: 'rate', label: 'Per stream' },
];

/** At most this many platforms get a colour of their own in a chart; the rest is "Other". */
const SHOWN = 8;

const MOVING =
    'transition-[height,width,opacity] duration-500 ease-out motion-reduce:transition-none';

/** A line is uncovered from left to right when it first shows. */
const WIPING =
    'transition-[clip-path] duration-700 ease-out motion-reduce:transition-none';

/** What one stream paid, or nothing where no stream was counted. */
function rate(income: number, streams: number): number | null {
    return streams > 0 ? income / streams : null;
}

/** False for the first frame, so bars and lines start empty and grow in. */
function useGrown(): boolean {
    const [grown, setGrown] = useState(false);

    useEffect(() => {
        const frame = requestAnimationFrame(() => setGrown(true));

        return () => cancelAnimationFrame(frame);
    }, []);

    return grown;
}

function Dot({ colour }: { colour: string }) {
    return (
        <span
            aria-hidden
            className="size-2.5 shrink-0 rounded-sm"
            style={{ backgroundColor: colour }}
        />
    );
}

/**
 * A small line of how a figure went month by month. Months without a
 * figure break the line.
 */
export function Sparkline({
    values,
    label,
    className,
}: {
    values: (number | null)[];
    label: string;
    className?: string;
}) {
    const grown = useGrown();
    const known = values.filter((value) => value !== null);
    const peak = Math.max(...known, 0);

    if (known.length < 2 || peak <= 0) {
        return null;
    }

    const step = 100 / (values.length - 1);
    const path = values
        .map((value, at) =>
            value === null
                ? null
                : `${values[at - 1] === null || at === 0 || values[at - 1] === undefined ? 'M' : 'L'}${(at * step).toFixed(2)},${(22 - (value / peak) * 20).toFixed(2)}`,
        )
        .filter((point) => point !== null)
        .join(' ');

    return (
        <svg
            role="img"
            aria-label={label}
            viewBox="0 0 100 24"
            preserveAspectRatio="none"
            className={cn('h-6 w-24 text-chart-1', WIPING, className)}
            style={{ clipPath: `inset(0 ${grown ? 0 : 100}% 0 0)` }}
        >
            <path
                d={path}
                fill="none"
                stroke="currentColor"
                strokeWidth={2}
                strokeLinejoin="round"
                strokeLinecap="round"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

/**
 * The months of a report as a chart: income or streams stacked by
 * platform, or what a stream paid as one line per platform.
 */
function Development({
    report,
    shown,
    colours,
    pointed,
    point,
    only,
}: {
    report: Report;
    shown: Platform[];
    colours: Map<string, string>;
    pointed: string | null;
    point: (key: string | null) => void;
    /** Show this one figure and no switch. */
    only?: Metric;
}) {
    const grown = useGrown();
    const [metric, setMetric] = useState<Metric>(only ?? 'total');
    const [month_, setMonth] = useState<number | null>(null);
    const months = report.months;
    const others = report.platforms.filter(
        (platform) => !shown.includes(platform),
    );
    const sum = (list: Platform[], series: 'total' | 'streams', at: number) =>
        list.reduce(
            (total, platform) => total + platform.series[series][at],
            0,
        );
    const series = metric === 'streams' ? 'streams' : 'total';
    const parts = [
        ...shown.map((platform) => ({
            key: platform.key,
            name: platform.name,
            colour: colours.get(platform.key) as string,
            values: platform.series[series],
            rates: platform.series.stream_total.map((income, at) =>
                rate(income, platform.series.streams[at]),
            ),
        })),
        ...(others.length > 0
            ? [
                  {
                      key: 'other',
                      name: `Other (${others.length})`,
                      colour: GREY,
                      values: months.map((_, at) => sum(others, series, at)),
                      rates: months.map((_, at) =>
                          rate(
                              others.reduce(
                                  (total, platform) =>
                                      total + platform.series.stream_total[at],
                                  0,
                              ),
                              sum(others, 'streams', at),
                          ),
                      ),
                  },
              ]
            : []),
    ];
    const totals = months.map((_, at) =>
        parts.reduce((total, part) => total + Math.max(part.values[at], 0), 0),
    );
    const peak =
        metric === 'rate'
            ? Math.max(...parts.flatMap((part) => part.rates.map(Number)), 0)
            : Math.max(...totals, 0);
    const every = Math.ceil(months.length / 12);
    const figure = (value: number | null) =>
        value === null
            ? '–'
            : metric === 'streams'
              ? quantity(Math.round(value))
              : money(value, undefined, metric === 'rate');

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div
                    role="group"
                    aria-label="What the chart shows"
                    className={cn(
                        'inline-flex rounded-md border p-0.5 text-xs',
                        only && 'invisible',
                    )}
                >
                    {METRICS.map((entry) => (
                        <button
                            key={entry.key}
                            type="button"
                            aria-pressed={metric === entry.key}
                            onClick={() => setMetric(entry.key)}
                            className={cn(
                                'rounded px-2.5 py-1 transition-colors',
                                metric === entry.key
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {entry.label}
                        </button>
                    ))}
                </div>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {month_ === null
                        ? `${month(months[0])} – ${month(months[months.length - 1])}`
                        : month(months[month_])}
                </span>
            </div>

            <div
                data-development={metric}
                className="relative h-56"
                onMouseLeave={() => setMonth(null)}
            >
                <div className="pointer-events-none absolute inset-x-0 top-0 bottom-5 flex flex-col justify-between">
                    {[1, 0.5, 0].map((line) => (
                        <div
                            key={line}
                            className="flex items-center gap-2 text-[10px] text-muted-foreground tabular-nums"
                        >
                            <span className="w-12 shrink-0 text-right">
                                {metric === 'rate'
                                    ? decimal(peak * line, 4)
                                    : compact(peak * line)}
                            </span>
                            <span className="h-px flex-1 bg-border" />
                        </div>
                    ))}
                </div>

                {metric === 'rate' ? (
                    <svg
                        role="img"
                        aria-label="What a stream paid, month by month, by platform"
                        viewBox="0 0 100 100"
                        preserveAspectRatio="none"
                        className={cn(
                            'absolute top-1.5 left-14 h-[calc(100%-1.625rem)] w-[calc(100%-3.5rem)]',
                            WIPING,
                        )}
                        style={{
                            clipPath: `inset(-4px ${grown ? -4 : 100}% -4px -4px)`,
                        }}
                    >
                        {parts.map((part) => {
                            // Each month sits over its column; a month alone between gaps is a dot.
                            const path = part.rates
                                .map((value, at) =>
                                    value === null || peak <= 0
                                        ? null
                                        : `${at === 0 || part.rates[at - 1] === null ? 'M' : 'L'}${(((at + 0.5) / months.length) * 100).toFixed(2)},${(100 - (value / peak) * 100).toFixed(2)} l0,0`,
                                )
                                .filter((entry) => entry !== null)
                                .join(' ');

                            return (
                                <path
                                    key={part.key}
                                    d={path}
                                    fill="none"
                                    stroke={part.colour}
                                    strokeWidth={2}
                                    strokeLinejoin="round"
                                    strokeLinecap="round"
                                    vectorEffect="non-scaling-stroke"
                                    className={cn(
                                        'transition-opacity',
                                        pointed !== null &&
                                            pointed !== part.key &&
                                            'opacity-15',
                                    )}
                                />
                            );
                        })}
                    </svg>
                ) : null}

                <div className="absolute inset-y-0 right-0 left-14 flex gap-0.5">
                    {months.map((name, at) => (
                        <div
                            key={name}
                            data-month={name}
                            onMouseEnter={() => setMonth(at)}
                            className={cn(
                                'flex min-w-0 flex-1 flex-col',
                                month_ === at && 'bg-muted/60',
                            )}
                        >
                            <div className="flex min-h-0 flex-1 flex-col-reverse gap-0.5 px-[12%] pt-1.5">
                                {metric !== 'rate' &&
                                    parts.map((part) => (
                                        <div
                                            key={part.key}
                                            onMouseEnter={() => point(part.key)}
                                            onMouseLeave={() => point(null)}
                                            className={cn(
                                                'w-full shrink-0 first:rounded-b-none last:rounded-t',
                                                MOVING,
                                                pointed !== null &&
                                                    pointed !== part.key &&
                                                    'opacity-25',
                                            )}
                                            style={{
                                                height: `${grown && peak > 0 ? (Math.max(part.values[at], 0) / peak) * 100 : 0}%`,
                                                backgroundColor: part.colour,
                                            }}
                                        />
                                    ))}
                            </div>
                            <span className="h-5 truncate pt-1 text-center text-[10px] text-muted-foreground">
                                {at % every === 0 ? month(name) : ''}
                            </span>
                        </div>
                    ))}
                </div>

                {month_ !== null && (
                    <div
                        role="status"
                        className={cn(
                            'pointer-events-none absolute top-0 z-10 w-56 rounded-lg border bg-popover p-2.5 text-xs text-popover-foreground shadow-md',
                            month_ < months.length / 2 ? 'right-0' : 'left-14',
                        )}
                    >
                        <div className="mb-1.5 flex items-baseline justify-between gap-3 font-medium">
                            <span>{month(months[month_])}</span>
                            {metric !== 'rate' && (
                                <span className="tabular-nums">
                                    {figure(totals[month_])}
                                </span>
                            )}
                        </div>
                        <ul className="grid gap-1">
                            {parts
                                .filter((part) =>
                                    metric === 'rate'
                                        ? part.rates[month_] !== null
                                        : part.values[month_] > 0,
                                )
                                .map((part) => (
                                    <li
                                        key={part.key}
                                        className="flex items-center gap-1.5"
                                    >
                                        <Dot colour={part.colour} />
                                        <span className="min-w-0 flex-1 truncate">
                                            {part.name}
                                        </span>
                                        <span className="tabular-nums">
                                            {figure(
                                                metric === 'rate'
                                                    ? part.rates[month_]
                                                    : part.values[month_],
                                            )}
                                        </span>
                                    </li>
                                ))}
                        </ul>
                    </div>
                )}
            </div>

            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                {parts.map((part) => (
                    <li
                        key={part.key}
                        onMouseEnter={() => point(part.key)}
                        onMouseLeave={() => point(null)}
                        className={cn(
                            'flex items-center gap-1.5 transition-opacity',
                            pointed !== null &&
                                pointed !== part.key &&
                                'opacity-40',
                        )}
                    >
                        <Dot colour={part.colour} />
                        {part.key !== 'other' && (
                            <PlatformIcon
                                platform={part.key}
                                name={part.name}
                            />
                        )}
                        {part.name}
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Rows ranked by their figure, each with a bar of its share of the largest. */
function Ranked({
    rows,
}: {
    rows: { label: ReactNode; total: number; units: number }[];
}) {
    const grown = useGrown();
    const peak = Math.max(...rows.map((row) => row.total), 0);

    return (
        <ul className="grid gap-2.5">
            {rows.map((row, position) => (
                <li key={position} className="grid gap-1 text-sm">
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="min-w-0 truncate">{row.label}</span>
                        <span className="flex shrink-0 items-baseline gap-3">
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {quantity(row.units)}
                            </span>
                            <Amount value={row.total} />
                        </span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className={cn(
                                'h-full rounded-full bg-chart-1',
                                MOVING,
                            )}
                            style={{
                                width: `${grown && peak > 0 ? Math.max((row.total / peak) * 100, 0) : 0}%`,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

/**
 * The income of a selection, the same on every page that has one: how
 * it developed, which platforms it came from and what they paid per
 * stream, what kind of use it was and where.
 */
export function IncomeReport({
    report,
    empty,
}: {
    report: Report;
    /** Said in place of the report when the selection has no income. Without it nothing is shown. */
    empty?: string;
}) {
    const grown = useGrown();
    const [pointed, point] = useState<string | null>(null);
    const counted = report.platforms.some(
        (platform) => platform.plays !== null,
    );

    if (report.total <= 0 && !counted) {
        return empty ? (
            <Panel title="Income">
                <p className="text-sm text-muted-foreground">{empty}</p>
            </Panel>
        ) : null;
    }

    // A platform keeps its colour wherever it appears: it follows its fixed place, not its rank here.
    const shown = report.platforms
        .filter((platform) => platform.total > 0)
        .slice(0, SHOWN)
        .sort((a, b) => a.position - b.position);
    const colours = new Map(
        shown.map((platform) => [platform.key, colourAt(platform.position)]),
    );
    const sums = report.platforms.reduce(
        (total, platform) => ({
            streams: total.streams + platform.streams,
            stream_total: total.stream_total + platform.stream_total,
            downloads: total.downloads + platform.downloads,
            download_total: total.download_total + platform.download_total,
        }),
        { streams: 0, stream_total: 0, downloads: 0, download_total: 0 },
    );
    const each = (income: number, units: number) => {
        const paid = rate(income, units);

        return paid === null ? (
            <span className="text-muted-foreground">–</span>
        ) : (
            <Amount value={paid} precise />
        );
    };

    return (
        <div data-income-report className="grid gap-6">
            {report.months.length > 0 && (
                <Panel
                    title="How the income developed"
                    description="Month by month and by platform. Point at a platform to follow it; switch to streams or to what a stream paid."
                >
                    <Development
                        report={report}
                        shown={shown}
                        colours={colours}
                        pointed={pointed}
                        point={point}
                    />
                </Panel>
            )}

            <Panel
                title="Platforms"
                description={
                    counted
                        ? 'What each platform paid, per stream and per download, and its own play count held against the streams on the statements for the same months.'
                        : 'What each platform paid, per stream and per download.'
                }
                flush
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Platform</TableHead>
                            <TableHead className="text-right">Income</TableHead>
                            <TableHead className="w-28">Share</TableHead>
                            <TableHead className="text-right">
                                Streams
                            </TableHead>
                            <TableHead className="text-right">
                                Per stream
                            </TableHead>
                            <TableHead>Trend</TableHead>
                            <TableHead className="text-right">
                                Downloads
                            </TableHead>
                            <TableHead className="text-right">
                                Per download
                            </TableHead>
                            {counted && (
                                <>
                                    <TableHead className="text-right">
                                        Counted by the platform
                                    </TableHead>
                                    <TableHead className="text-right">
                                        On statements
                                    </TableHead>
                                </>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {report.platforms.map((platform) => (
                            <TableRow
                                key={platform.key}
                                data-platform={platform.key}
                                onMouseEnter={() =>
                                    point(
                                        colours.has(platform.key)
                                            ? platform.key
                                            : 'other',
                                    )
                                }
                                onMouseLeave={() => point(null)}
                            >
                                <TableCell>
                                    <span className="flex items-center gap-2">
                                        <Dot
                                            colour={
                                                colours.get(platform.key) ??
                                                GREY
                                            }
                                        />
                                        <PlatformIcon
                                            platform={platform.key}
                                            name={platform.name}
                                        />
                                        {platform.name}
                                    </span>
                                </TableCell>
                                <TableCell className="text-right">
                                    <Amount value={platform.total} />
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full',
                                                    MOVING,
                                                )}
                                                style={{
                                                    width: `${grown && report.total > 0 ? Math.max((platform.total / report.total) * 100, 0) : 0}%`,
                                                    backgroundColor:
                                                        colours.get(
                                                            platform.key,
                                                        ) ?? GREY,
                                                }}
                                            />
                                        </div>
                                        <span className="w-9 text-right text-xs text-muted-foreground tabular-nums">
                                            {report.total > 0
                                                ? `${Math.round((platform.total / report.total) * 100)}%`
                                                : '–'}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {platform.streams > 0
                                        ? quantity(platform.streams)
                                        : '–'}
                                </TableCell>
                                <TableCell className="text-right">
                                    {each(
                                        platform.stream_total,
                                        platform.streams,
                                    )}
                                </TableCell>
                                <TableCell>
                                    <Sparkline
                                        label={`What a stream on ${platform.name} paid, month by month`}
                                        values={platform.series.stream_total.map(
                                            (income, at) =>
                                                rate(
                                                    income,
                                                    platform.series.streams[at],
                                                ),
                                        )}
                                    />
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {platform.downloads > 0
                                        ? quantity(platform.downloads)
                                        : '–'}
                                </TableCell>
                                <TableCell className="text-right">
                                    {each(
                                        platform.download_total,
                                        platform.downloads,
                                    )}
                                </TableCell>
                                {counted && (
                                    <>
                                        <TableCell className="text-right tabular-nums">
                                            {platform.plays === null ? (
                                                <span className="text-muted-foreground">
                                                    –
                                                </span>
                                            ) : (
                                                <span
                                                    title={`${date(platform.plays_from)} – ${date(platform.plays_to)}`}
                                                >
                                                    {quantity(platform.plays)}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {platform.plays === null ||
                                            platform.reported_streams ===
                                                null ? (
                                                <span className="text-muted-foreground">
                                                    –
                                                </span>
                                            ) : (
                                                <>
                                                    {quantity(
                                                        platform.reported_streams,
                                                    )}
                                                    {platform.plays > 0 && (
                                                        <span className="ml-1.5 text-xs text-muted-foreground">
                                                            {Math.round(
                                                                (platform.reported_streams /
                                                                    platform.plays) *
                                                                    100,
                                                            )}
                                                            %
                                                        </span>
                                                    )}
                                                </>
                                            )}
                                        </TableCell>
                                    </>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell>All platforms</TableCell>
                            <TableCell className="text-right">
                                <Amount value={report.total} />
                            </TableCell>
                            <TableCell />
                            <TableCell className="text-right tabular-nums">
                                {quantity(sums.streams)}
                            </TableCell>
                            <TableCell className="text-right">
                                {each(sums.stream_total, sums.streams)}
                            </TableCell>
                            <TableCell />
                            <TableCell className="text-right tabular-nums">
                                {quantity(sums.downloads)}
                            </TableCell>
                            <TableCell className="text-right">
                                {each(sums.download_total, sums.downloads)}
                            </TableCell>
                            {counted && <TableCell colSpan={2} />}
                        </TableRow>
                    </TableFooter>
                </Table>
            </Panel>

            {report.total > 0 && (
                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title="What it was"
                        description="Streams, downloads, records and rights by what they brought in, with how many of each."
                    >
                        <SegmentedBar
                            format={(value) => <Amount value={value} />}
                            segments={report.kinds.map((kind) => ({
                                label: `${kind.label} (${quantity(kind.units)})`,
                                value: kind.total,
                            }))}
                        />
                    </Panel>
                    <Panel
                        title="Where it was"
                        description="The countries that brought in most."
                    >
                        <Ranked
                            rows={report.regions.map((region) => ({
                                label: country(region.label),
                                total: region.total,
                                units: region.units,
                            }))}
                        />
                    </Panel>
                </div>
            )}
        </div>
    );
}

/**
 * What the platforms counted themselves, drawn as the income is: how the
 * plays developed by platform, and each platform's count next to what
 * the statements have for the same months.
 */
export function PlayReport({ report }: { report: Report }) {
    const grown = useGrown();
    const [pointed, point] = useState<string | null>(null);

    if (report.platforms.length === 0) {
        return null;
    }

    const shown = report.platforms
        .slice(0, SHOWN)
        .sort((a, b) => a.position - b.position);
    const colours = new Map(
        shown.map((platform) => [platform.key, colourAt(platform.position)]),
    );
    const all = report.platforms.reduce(
        (total, platform) => total + platform.streams,
        0,
    );
    const reported = report.platforms.reduce(
        (total, platform) => total + (platform.reported_streams ?? 0),
        0,
    );
    const share = (part: number, whole: number) =>
        whole > 0 ? `${Math.round((part / whole) * 100)}%` : '–';

    return (
        <div data-play-report className="grid gap-6">
            <Panel
                title="How the plays developed"
                description="What the platforms counted, month by month. Point at a platform to follow it."
            >
                <Development
                    report={report}
                    shown={shown}
                    colours={colours}
                    pointed={pointed}
                    point={point}
                    only="streams"
                />
            </Panel>

            <Panel
                title="Platforms"
                description="What each platform counted, and the streams the statements have for the same months. Statements arrive late, so the last months are often missing there."
                flush
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Platform</TableHead>
                            <TableHead className="text-right">
                                Counted by the platform
                            </TableHead>
                            <TableHead className="w-28">Share</TableHead>
                            <TableHead>Trend</TableHead>
                            <TableHead className="text-right">
                                On statements
                            </TableHead>
                            <TableHead>Counted from</TableHead>
                            <TableHead>to</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {report.platforms.map((platform) => (
                            <TableRow
                                key={platform.key}
                                data-platform={platform.key}
                                onMouseEnter={() =>
                                    point(
                                        colours.has(platform.key)
                                            ? platform.key
                                            : 'other',
                                    )
                                }
                                onMouseLeave={() => point(null)}
                            >
                                <TableCell>
                                    <span className="flex items-center gap-2">
                                        <Dot
                                            colour={
                                                colours.get(platform.key) ??
                                                GREY
                                            }
                                        />
                                        <PlatformIcon
                                            platform={platform.key}
                                            name={platform.name}
                                        />
                                        {platform.name}
                                    </span>
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {quantity(platform.streams)}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full',
                                                    MOVING,
                                                )}
                                                style={{
                                                    width: `${grown && all > 0 ? (platform.streams / all) * 100 : 0}%`,
                                                    backgroundColor:
                                                        colours.get(
                                                            platform.key,
                                                        ) ?? GREY,
                                                }}
                                            />
                                        </div>
                                        <span className="w-9 text-right text-xs text-muted-foreground tabular-nums">
                                            {share(platform.streams, all)}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Sparkline
                                        label={`Plays counted by ${platform.name}, month by month`}
                                        values={platform.series.streams}
                                    />
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {quantity(platform.reported_streams ?? 0)}
                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                        {share(
                                            platform.reported_streams ?? 0,
                                            platform.streams,
                                        )}
                                    </span>
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {date(platform.plays_from)}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {date(platform.plays_to)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell>All platforms</TableCell>
                            <TableCell className="text-right tabular-nums">
                                {quantity(all)}
                            </TableCell>
                            <TableCell colSpan={2} />
                            <TableCell className="text-right tabular-nums">
                                {quantity(reported)}
                                <span className="ml-1.5 text-xs text-muted-foreground">
                                    {share(reported, all)}
                                </span>
                            </TableCell>
                            <TableCell colSpan={2} />
                        </TableRow>
                    </TableFooter>
                </Table>
            </Panel>
        </div>
    );
}

/**
 * What earned most in a selection, with its last twelve months: what is
 * still moving and what has gone quiet.
 */
export function MoversPanel({
    movers,
    to,
    title,
    plays = false,
}: {
    movers: Movers;
    to: 'release' | 'track';
    title: string;
    /** The figures are counted plays, not income. */
    plays?: boolean;
}) {
    const figure = (value: number) =>
        plays ? quantity(value) : <Amount value={value} />;

    if (movers.rows.length === 0) {
        return null;
    }

    const last = movers.months[movers.months.length - 1];

    return (
        <Panel
            title={title}
            description={`By ${plays ? 'counted plays' : 'income'}, each with its twelve months up to ${month(last)}. The last three months are held against the three before.`}
            flush
        >
            <Table data-movers>
                <TableHeader>
                    <TableRow>
                        <TableHead>
                            {to === 'release' ? 'Release' : 'Track'}
                        </TableHead>
                        <TableHead>Twelve months</TableHead>
                        <TableHead className="text-right">
                            Last three months
                        </TableHead>
                        {!plays && (
                            <>
                                <TableHead className="text-right">
                                    Streams
                                </TableHead>
                                <TableHead className="text-right">
                                    Per stream
                                </TableHead>
                            </>
                        )}
                        <TableHead className="text-right">
                            {plays ? 'Counted' : 'Income'}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {movers.rows.map((row) => {
                        const change =
                            row.before > 0
                                ? Math.round(
                                      ((row.recent - row.before) / row.before) *
                                          100,
                                  )
                                : null;
                        const paid = rate(row.stream_total, row.streams);

                        return (
                            <TableRow key={row.id}>
                                <TableCell>
                                    <RecordLink to={to} id={row.id}>
                                        {row.label}
                                    </RecordLink>
                                </TableCell>
                                <TableCell>
                                    <Sparkline
                                        label={`${plays ? 'Plays' : 'Income'} of ${row.label}, month by month`}
                                        values={row.series}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    {row.recent > 0 ? (
                                        <span className="inline-flex items-baseline gap-2">
                                            {change !== null && (
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {change > 0
                                                        ? '▲'
                                                        : change < 0
                                                          ? '▼'
                                                          : ''}{' '}
                                                    {change > 0 ? '+' : ''}
                                                    {change}%
                                                </span>
                                            )}
                                            <span className="tabular-nums">
                                                {figure(row.recent)}
                                            </span>
                                        </span>
                                    ) : (
                                        <Badge variant="outline">quiet</Badge>
                                    )}
                                </TableCell>
                                {!plays && (
                                    <>
                                        <TableCell className="text-right tabular-nums">
                                            {row.streams > 0
                                                ? quantity(row.streams)
                                                : '–'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {paid === null ? (
                                                <span className="text-muted-foreground">
                                                    –
                                                </span>
                                            ) : (
                                                <Amount value={paid} precise />
                                            )}
                                        </TableCell>
                                    </>
                                )}
                                <TableCell className="text-right tabular-nums">
                                    {figure(row.total)}
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </Panel>
    );
}
