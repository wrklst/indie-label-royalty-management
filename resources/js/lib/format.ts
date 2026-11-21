const formatters = new Map<string, Intl.NumberFormat>();

function formatter(
    key: string,
    options: Intl.NumberFormatOptions,
): Intl.NumberFormat {
    let instance = formatters.get(key);

    if (!instance) {
        instance = new Intl.NumberFormat('en-GB', options);
        formatters.set(key, instance);
    }

    return instance;
}

/**
 * Format a decimal string as money. Streaming lines are often fractions of
 * a cent, so `precise` keeps up to six decimals instead of rounding to 0.00.
 */
export function money(
    value: string | number | null | undefined,
    currency = 'EUR',
    precise = false,
): string {
    if (value === null || value === undefined || value === '') {
        return '–';
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return '–';
    }

    return formatter(`${currency}-${precise}`, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: precise ? 6 : 2,
    }).format(amount === 0 ? 0 : amount);
}

export function quantity(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '–';
    }

    return formatter('quantity', { maximumFractionDigits: 2 }).format(
        Number(value),
    );
}

/** A whole number such as a row count: "12,345". */
export function count(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '–';
    }

    return formatter('count', { maximumFractionDigits: 0 }).format(
        Number(value),
    );
}

/** A short number for a chart axis: "1.2K". */
export function compact(value: number): string {
    return formatter('compact', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

/** A number with at most `digits` decimals, for a value that is neither money nor a count. */
export function decimal(value: number, digits: number): string {
    return formatter(`decimal-${digits}`, {
        maximumFractionDigits: digits,
    }).format(value);
}

/** A count with its noun: plural(1, 'track') is "1 track", plural(2, 'track') is "2 tracks". */
export function plural(
    amount: number,
    singular: string,
    plural = `${singular}s`,
): string {
    return `${count(amount)} ${amount === 1 ? singular : plural}`;
}

/**
 * Format a split fraction such as "0.250000000" as "25%". `digits` is the
 * most decimals shown.
 */
export function percent(
    fraction: string | number | null | undefined,
    digits = 4,
): string {
    if (fraction === null || fraction === undefined || fraction === '') {
        return '–';
    }

    return `${formatter(`percent-${digits}`, { maximumFractionDigits: digits }).format(Number(fraction) * 100)}%`;
}

/** A file size: "3.4 MB". */
export function fileSize(bytes: number): string {
    if (bytes >= 1024 ** 3) {
        return `${formatter('size-2', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(bytes / 1024 ** 3)} GB`;
    }

    if (bytes >= 1024 ** 2) {
        return `${formatter('size-1', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(bytes / 1024 ** 2)} MB`;
    }

    return `${formatter('size-0', { maximumFractionDigits: 0 }).format(Math.max(1, bytes / 1024))} KB`;
}

// Month names are spelled out here instead of asking Intl: Node and Safari
// disagree on "Sep" vs "Sept", which breaks hydration of server-rendered pages.
const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

export function date(value: string | null | undefined): string {
    if (!value) {
        return '–';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
    const name = match ? MONTHS[Number(match[2]) - 1] : undefined;

    return match && name ? `${match[3]} ${name} ${match[1]}` : value;
}

/** A moment from an ISO string, in the browser's own time: "19 Nov 2026, 14:03". */
export function dateTime(value: string | null | undefined): string {
    if (!value) {
        return '–';
    }

    const moment = new Date(value);

    if (Number.isNaN(moment.getTime())) {
        return value;
    }

    const pad = (part: number) => String(part).padStart(2, '0');

    return `${pad(moment.getDate())} ${MONTHS[moment.getMonth()]} ${moment.getFullYear()}, ${pad(moment.getHours())}:${pad(moment.getMinutes())}`;
}

/** The local date as Y-m-d: toISOString() is UTC and gives yesterday shortly after midnight. */
export function today(): string {
    return new Date().toLocaleDateString('sv-SE');
}

export function month(value: string | null | undefined): string {
    if (!value) {
        return '–';
    }

    const match = /^(\d{4})-(\d{2})/.exec(value);
    const name = match ? MONTHS[Number(match[2]) - 1] : undefined;

    return match && name ? `${name} ${match[1].slice(2)}` : value;
}

let regionNames: Intl.DisplayNames | null = null;

export function country(code: string | null | undefined): string {
    if (!code) {
        return 'Unknown';
    }

    try {
        regionNames ??= new Intl.DisplayNames(['en'], { type: 'region' });

        return regionNames.of(code) ?? code;
    } catch {
        return code;
    }
}

export function isNegative(value: string | number | null | undefined): boolean {
    return Number(value) < 0;
}

export function duration(seconds: number | null | undefined): string {
    if (!seconds) {
        return '–';
    }

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}
