import { useMemo, useState } from 'react';

export type SortDirection = 'asc' | 'desc';

/** The column a table is sorted by; both null in its default order. */
export type SortState = {
    sort: string | null;
    direction: SortDirection | null;
};

/** What a sortable column header needs: the active order and how to change it. */
export type Sorter = SortState & { onSort: (next: SortState) => void };

export type SortValue = string | number | boolean | null | undefined;

const NUMERIC = /^-?\d+(\.\d+)?$/;
const ISO_DATE = /^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?)?/;

/**
 * The order a click on a column header leads to: its first direction, then
 * the opposite one, then back to the default order.
 */
export function nextSort(
    current: SortState,
    column: string,
    first: SortDirection = 'asc',
): SortState {
    if (current.sort !== column || current.direction === null) {
        return { sort: column, direction: first };
    }

    if (current.direction === first) {
        return { sort: column, direction: first === 'asc' ? 'desc' : 'asc' };
    }

    return { sort: null, direction: null };
}

function isEmpty(value: SortValue): value is null | undefined | '' {
    return value === null || value === undefined || value === '';
}

function toNumber(value: string | number | boolean): number | null {
    if (typeof value === 'number') {
        return Number.isNaN(value) ? null : value;
    }

    if (typeof value === 'boolean') {
        return value ? 1 : 0;
    }

    return NUMERIC.test(value.trim()) ? Number(value) : null;
}

function toTime(value: string | number | boolean): number | null {
    if (typeof value !== 'string' || !ISO_DATE.test(value)) {
        return null;
    }

    const time = Date.parse(value.replace(' ', 'T'));

    return Number.isNaN(time) ? null : time;
}

/**
 * Compare two cell values ascending: numbers and decimal strings by amount,
 * ISO dates by date, anything else as text without regard to case.
 */
export function compareValues(
    a: string | number | boolean,
    b: string | number | boolean,
): number {
    const numberA = toNumber(a);
    const numberB = toNumber(b);

    if (numberA !== null && numberB !== null) {
        return numberA - numberB;
    }

    const timeA = toTime(a);
    const timeB = toTime(b);

    if (timeA !== null && timeB !== null) {
        return timeA - timeB;
    }

    return String(a).localeCompare(String(b), 'en', {
        numeric: true,
        sensitivity: 'base',
    });
}

/**
 * Sort a copy of the rows by one column. Empty values come last whichever
 * way the column is sorted, and equal rows keep the order they came in.
 */
export function sortRows<T>(
    rows: readonly T[],
    value: (row: T) => SortValue,
    direction: SortDirection,
): T[] {
    const sign = direction === 'desc' ? -1 : 1;

    return [...rows].sort((rowA, rowB) => {
        const a = value(rowA);
        const b = value(rowB);

        if (isEmpty(a) || isEmpty(b)) {
            return Number(isEmpty(a)) - Number(isEmpty(b));
        }

        return sign * compareValues(a, b);
    });
}

/**
 * Sort a table that has all of its rows in the browser. Until a header is
 * clicked the rows stay in the order the server sent them. Define `columns`
 * outside the component so the sorted rows are only recomputed when needed.
 */
export function useTableSort<T, K extends string>(
    rows: readonly T[],
    columns: Record<K, (row: T) => SortValue>,
    initial: SortState = { sort: null, direction: null },
): { rows: T[]; sorter: Sorter } {
    const [state, setState] = useState<SortState>(initial);

    const sorted = useMemo(() => {
        const value =
            state.sort !== null && state.sort in columns
                ? columns[state.sort as K]
                : null;

        return value && state.direction
            ? sortRows(rows, value, state.direction)
            : [...rows];
    }, [rows, columns, state]);

    return { rows: sorted, sorter: { ...state, onSort: setState } };
}

/**
 * Sorting for a paginated list, which is ordered by the server: the order
 * travels in the query string next to the other filters. A page with
 * several sortable lists gives each a `prefix` ("files" uses `files_sort`
 * and `files_direction`).
 */
export function serverSorter(
    filters: object,
    apply: (changes: never, debounce?: boolean) => void,
    prefix = '',
): Sorter {
    const name = prefix === '' ? '' : `${prefix}_`;
    const query = filters as Record<string, unknown>;
    const sort = query[`${name}sort`];
    const direction = query[`${name}direction`];

    return {
        sort: typeof sort === 'string' ? sort : null,
        direction:
            direction === 'asc' || direction === 'desc' ? direction : null,
        onSort: (next) =>
            (apply as (changes: Record<string, string | null>) => void)({
                [`${name}sort`]: next.sort,
                [`${name}direction`]: next.direction,
            }),
    };
}
