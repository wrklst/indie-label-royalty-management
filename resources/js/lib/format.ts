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

/** Format a split fraction such as "0.250000000" as "25%". */
export function percent(fraction: string | number | null | undefined): string {
    if (fraction === null || fraction === undefined || fraction === '') {
        return '–';
    }

    return `${formatter('percent', { maximumFractionDigits: 4 }).format(Number(fraction) * 100)}%`;
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
