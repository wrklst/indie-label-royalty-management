import { useSyncExternalStore } from 'react';
import { handbook } from '@/routes';
import { panel } from '@/routes/handbook';

/**
 * The handbook panel beside the page. Like the audio player its state
 * lives outside the React tree, so it stays open while you move through
 * the app, and it is remembered across reloads.
 */

export type HelpArticle = {
    slug: string;
    title: string;
    chapter: string;
    html: string;
};

export type HelpState = {
    article: HelpArticle | null;
    /** Articles read before the current one, for the back button. */
    history: string[];
    minimized: boolean;
    loading: boolean;
    failed: boolean;
};

const STORAGE_KEY = 'royalties.handbook';

const EMPTY: HelpState = {
    article: null,
    history: [],
    minimized: false,
    loading: false,
    failed: false,
};

/**
 * The article that explains a page, by the start of its address. The
 * first match wins, so longer addresses come first.
 */
export const HELP_TOPICS: [string, string][] = [
    ['/settings/bandcamp', '11-bandcamp'],
    ['/settings/label', '41-label-settings'],
    ['/settings', '40-team-roles-and-sign-in'],
    ['/data-imports', '10-importing-statements'],
    ['/transactions', '31-transactions'],
    ['/periods', '32-periods-statements-and-payouts'],
    ['/statements', '32-periods-statements-and-payouts'],
    ['/payouts', '32-periods-statements-and-payouts'],
    ['/profitability', '33-profitability-and-label-costs'],
    ['/label-costs', '33-profitability-and-label-costs'],
    ['/royalty-tools', '34-mechanicals-ppd-and-reserves'],
    ['/catalog', '20-releases'],
    ['/releases', '20-releases'],
    ['/tracks', '21-tracks-artists-and-items'],
    ['/artists', '21-tracks-artists-and-items'],
    ['/items', '21-tracks-artists-and-items'],
    ['/airplays', '23-airplay'],
    ['/contracts', '30-contracts-payees-and-ledgers'],
    ['/payees', '30-contracts-payees-and-ledgers'],
    ['/ledgers', '30-contracts-payees-and-ledgers'],
];

export const DEFAULT_TOPIC = '01-introduction';

export function topicFor(path: string): string {
    return (
        HELP_TOPICS.find(
            ([prefix]) => path === prefix || path.startsWith(`${prefix}/`),
        )?.[1] ?? DEFAULT_TOPIC
    );
}

let state: HelpState = EMPTY;
let restored = false;

const listeners = new Set<() => void>();

function set(patch: Partial<HelpState>): void {
    state = { ...state, ...patch };
    listeners.forEach((listener) => listener());
    save();
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Read a slice of the panel state. The selector must return a primitive
 * or a value taken from the state as is.
 */
export function useHelp<T>(selector: (state: HelpState) => T): T {
    return useSyncExternalStore(
        subscribe,
        () => selector(state),
        () => selector(EMPTY),
    );
}

function save(): void {
    try {
        if (!state.article) {
            window.localStorage.removeItem(STORAGE_KEY);

            return;
        }

        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                slug: state.article.slug,
                history: state.history,
                minimized: state.minimized,
            }),
        );
    } catch {
        // Storage can be full or switched off; the panel works without it.
    }
}

async function load(slug: string): Promise<void> {
    set({ loading: true, failed: false });

    try {
        const response = await fetch(panel.url(slug), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        set({
            article: (await response.json()) as HelpArticle,
            loading: false,
        });
    } catch {
        set({ loading: false, failed: true });
    }
}

/** Show an article, remembering the one that was open for the back button. */
export function openHelp(slug: string): void {
    const current = state.article?.slug;

    set({
        minimized: false,
        history:
            current && current !== slug
                ? [...state.history, current]
                : state.history,
    });

    if (current !== slug || state.failed) {
        void load(slug);
    }
}

export function helpBack(): void {
    const slug = state.history.at(-1);

    if (slug) {
        set({ history: state.history.slice(0, -1) });
        void load(slug);
    }
}

export function minimizeHelp(minimized: boolean): void {
    set({ minimized });
}

export function closeHelp(): void {
    set({ ...EMPTY });
}

/** The handbook article a link points at, if it points at one. */
export function handbookSlug(href: string): string | null {
    const prefix = handbook.url().replace(/\/$/, '');
    const path = new URL(href, window.location.href).pathname;

    return path.startsWith(`${prefix}/`)
        ? (/^[\w-]+$/.exec(path.slice(prefix.length + 1))?.[0] ?? null)
        : null;
}

/** Bring back the article that was open before the page was reloaded. */
export function restoreHelp(): void {
    if (restored || typeof window === 'undefined') {
        return;
    }

    restored = true;

    try {
        const kept = JSON.parse(
            window.localStorage.getItem(STORAGE_KEY) ?? 'null',
        ) as { slug?: unknown; history?: unknown; minimized?: unknown } | null;

        if (kept && typeof kept.slug === 'string') {
            state = {
                ...state,
                minimized: kept.minimized === true,
                history: Array.isArray(kept.history)
                    ? kept.history.filter(
                          (entry): entry is string => typeof entry === 'string',
                      )
                    : [],
            };
            void load(kept.slug);
        }
    } catch {
        // A broken entry is ignored.
    }
}
