import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { show as showFile } from '@/routes/release-files';

/**
 * The app-wide audio player. Its state and its single <audio> element live
 * in this module, outside the React tree, so no page visit, layout change
 * or remount can interrupt playback. Components read it with usePlayer()
 * and change it through the exported actions.
 */

export type PlayerTrack = {
    /** Track id; a track is in the queue at most once. */
    id: number;
    fileId: number;
    title: string;
    artist: string | null;
    release: { id: number; name: string } | null;
    coverFileId: number | null;
};

export type PlayerState = {
    queue: PlayerTrack[];
    index: number;
    playing: boolean;
    position: number;
    duration: number;
    volume: number;
    muted: boolean;
    /** Why the current track does not play, if it does not. */
    error: string | null;
    /** Track ids that failed to load since the page was opened. */
    failed: number[];
};

type Scope = { labelId: number; userId: number };

type Stored = Scope & {
    queue: PlayerTrack[];
    index: number;
    position: number;
    volume: number;
    muted: boolean;
};

const STORAGE_KEY = 'royalties.player';
const SKIP_FAILED_AFTER_MS = 2500;
const SAVE_POSITION_EVERY_MS = 5000;

const EMPTY: PlayerState = {
    queue: [],
    index: 0,
    playing: false,
    position: 0,
    duration: 0,
    volume: 1,
    muted: false,
    error: null,
    failed: [],
};

let state: PlayerState = EMPTY;
let scope: Scope | null = null;
let audio: HTMLAudioElement | null = null;
/** The file the audio element currently points at. */
let loadedFileId: number | null = null;
/** Where to continue once the metadata of a restored track has loaded. */
let pendingPosition = 0;
/** Whether playback should go on when the next track is loaded. */
let wantsToPlay = false;
let skipTimer: number | null = null;
let positionSavedAt = 0;

const listeners = new Set<() => void>();

function set(patch: Partial<PlayerState>): void {
    state = { ...state, ...patch };
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Read a slice of the player state. The selector must return a primitive
 * or a value taken from the state as is, never a new object.
 */
export function usePlayer<T>(selector: (state: PlayerState) => T): T {
    return useSyncExternalStore(
        subscribe,
        () => selector(state),
        () => selector(EMPTY),
    );
}

export function currentTrack(from: PlayerState = state): PlayerTrack | null {
    return from.queue[from.index] ?? null;
}

function save(): void {
    if (!scope) {
        return;
    }

    try {
        if (state.queue.length === 0) {
            window.localStorage.removeItem(STORAGE_KEY);

            return;
        }

        const stored: Stored = {
            ...scope,
            queue: state.queue,
            index: state.index,
            position: audio && loadedFileId ? audio.currentTime : 0,
            volume: state.volume,
            muted: state.muted,
        };

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
        positionSavedAt = Date.now();
    } catch {
        // Storage can be full or switched off; the player works without it.
    }
}

function isTrack(value: unknown): value is PlayerTrack {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const track = value as Record<string, unknown>;

    return (
        typeof track.id === 'number' &&
        typeof track.fileId === 'number' &&
        typeof track.title === 'string'
    );
}

function read(): Stored | null {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const stored = raw ? (JSON.parse(raw) as Partial<Stored>) : null;

        if (
            !stored ||
            !Array.isArray(stored.queue) ||
            !stored.queue.every(isTrack) ||
            typeof stored.labelId !== 'number' ||
            typeof stored.userId !== 'number'
        ) {
            return null;
        }

        const volume = Number(stored.volume);

        return {
            labelId: stored.labelId,
            userId: stored.userId,
            queue: stored.queue,
            index: Math.min(
                Math.max(0, Math.trunc(Number(stored.index)) || 0),
                Math.max(0, stored.queue.length - 1),
            ),
            position: Math.max(0, Number(stored.position) || 0),
            volume: Number.isFinite(volume)
                ? Math.min(1, Math.max(0, volume))
                : 1,
            muted: stored.muted === true,
        };
    } catch {
        return null;
    }
}

function clearSkipTimer(): void {
    if (skipTimer !== null) {
        window.clearTimeout(skipTimer);
        skipTimer = null;
    }
}

/** The address the player streams a file from: its MP3 playback copy. */
function streamUrl(fileId: number): string {
    return showFile.url(fileId, { query: { stream: 1 } });
}

const UNPLAYABLE = 'This track cannot be played';
const PREPARING = 'Playback copy is still being prepared';

/**
 * The audio element does not say why loading failed, so ask the server:
 * 409 and 404 mean the MP3 copy of the master is not there yet.
 */
function explainFailure(track: PlayerTrack, suffix: string): void {
    fetch(streamUrl(track.fileId), {
        method: 'HEAD',
        credentials: 'same-origin',
    })
        .then((response) => {
            if (
                (response.status === 409 || response.status === 404) &&
                currentTrack()?.id === track.id &&
                state.error?.startsWith(UNPLAYABLE)
            ) {
                set({ error: `${PREPARING}${suffix}` });
            }
        })
        .catch(() => {
            // Offline or interrupted: the general message stays.
        });
}

function fail(): void {
    const track = currentTrack();

    if (!track || !audio || loadedFileId !== track.fileId) {
        return;
    }

    const hasNext = state.index < state.queue.length - 1;
    const suffix = hasNext ? '. Skipping to the next one…' : '.';

    set({
        playing: false,
        error: `${UNPLAYABLE}${suffix}`,
        failed: state.failed.includes(track.id)
            ? state.failed
            : [...state.failed, track.id],
    });
    explainFailure(track, suffix);

    clearSkipTimer();

    if (!hasNext) {
        wantsToPlay = false;

        return;
    }

    skipTimer = window.setTimeout(() => {
        skipTimer = null;

        if (currentTrack()?.id === track.id) {
            go(state.index + 1, wantsToPlay);
        }
    }, SKIP_FAILED_AFTER_MS);
}

function element(): HTMLAudioElement {
    if (audio) {
        return audio;
    }

    const created = document.createElement('audio');
    created.preload = 'metadata';
    created.dataset.player = 'global';
    created.hidden = true;

    created.addEventListener('play', () => set({ playing: true }));
    created.addEventListener('pause', () => {
        set({ playing: false });
        save();
    });
    created.addEventListener('timeupdate', () => {
        set({ position: created.currentTime });

        if (Date.now() - positionSavedAt > SAVE_POSITION_EVERY_MS) {
            save();
        }
    });
    created.addEventListener('durationchange', () =>
        set({
            duration: Number.isFinite(created.duration) ? created.duration : 0,
        }),
    );
    created.addEventListener('loadedmetadata', () => {
        if (pendingPosition > 0 && pendingPosition < created.duration) {
            created.currentTime = pendingPosition;
        }

        pendingPosition = 0;
    });
    created.addEventListener('ended', () => {
        if (state.index < state.queue.length - 1) {
            go(state.index + 1, true);

            return;
        }

        wantsToPlay = false;
        created.currentTime = 0;
        set({ playing: false, position: 0 });
        save();
    });
    created.addEventListener('error', fail);

    document.body.appendChild(created);
    window.addEventListener('pagehide', save);
    registerMediaSession();
    audio = created;

    return created;
}

function start(): void {
    const target = element();

    wantsToPlay = true;
    target.play().catch((reason: unknown) => {
        const name = reason instanceof DOMException ? reason.name : '';

        // AbortError: another track was loaded meanwhile. NotSupportedError
        // is reported by the element's own error event.
        if (name === 'NotAllowedError') {
            wantsToPlay = false;
            set({ playing: false });
        }
    });
}

/**
 * Point the audio element at the current track of the queue.
 */
function load(play: boolean, position = 0): void {
    const track = currentTrack();

    clearSkipTimer();
    wantsToPlay = play;

    if (!track) {
        unload();

        return;
    }

    const target = element();

    loadedFileId = track.fileId;
    pendingPosition = position;
    target.src = streamUrl(track.fileId);
    target.load();
    set({ position, duration: 0, error: null, playing: false });
    updateMediaSession(track);

    if (play) {
        start();
    }

    save();
}

function unload(): void {
    clearSkipTimer();
    wantsToPlay = false;
    loadedFileId = null;
    pendingPosition = 0;

    if (audio) {
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
    }

    if ('mediaSession' in navigator) {
        navigator.mediaSession.metadata = null;
    }
}

function go(index: number, play: boolean): void {
    if (index < 0 || index >= state.queue.length) {
        return;
    }

    set({ index });
    load(play);
}

function registerMediaSession(): void {
    if (!('mediaSession' in navigator)) {
        return;
    }

    const handlers: [MediaSessionAction, () => void][] = [
        ['play', resume],
        ['pause', pause],
        ['previoustrack', previous],
        ['nexttrack', next],
    ];

    for (const [action, handler] of handlers) {
        try {
            navigator.mediaSession.setActionHandler(action, handler);
        } catch {
            // The browser does not know this action.
        }
    }
}

function updateMediaSession(track: PlayerTrack): void {
    if (!('mediaSession' in navigator) || !('MediaMetadata' in window)) {
        return;
    }

    navigator.mediaSession.metadata = new MediaMetadata({
        title: track.title,
        artist: track.artist ?? '',
        album: track.release?.name ?? '',
        artwork: track.coverFileId
            ? [
                  {
                      src: showFile.url(track.coverFileId, {
                          query: { size: 'medium' },
                      }),
                  },
              ]
            : [],
    });
}

/**
 * Tie the player to the signed-in user and label. Whenever either changes,
 * or audio is not available to this user, the queue is dropped; otherwise
 * a queue saved by an earlier page load is restored, paused.
 */
function syncScope(next: Scope | null): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (
        next !== null &&
        scope?.labelId === next.labelId &&
        scope.userId === next.userId
    ) {
        return;
    }

    const stored = read();

    scope = next;

    if (
        next &&
        stored &&
        stored.labelId === next.labelId &&
        stored.userId === next.userId
    ) {
        if (state.queue.length > 0) {
            unload();
        }

        set({
            ...EMPTY,
            queue: stored.queue,
            index: stored.index,
            volume: stored.volume,
            muted: stored.muted,
        });

        if (stored.queue.length > 0) {
            const target = element();

            target.volume = stored.volume;
            target.muted = stored.muted;
            load(false, stored.position);
        }

        return;
    }

    unload();
    set({ ...EMPTY, volume: state.volume, muted: state.muted });

    try {
        if (stored) {
            window.localStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // Nothing to clean up without storage.
    }
}

/**
 * Start a track right away. It moves to the front of the queue; everything
 * else that was queued stays behind it.
 */
export function playNow(track: PlayerTrack): void {
    set({
        queue: [track, ...state.queue.filter((entry) => entry.id !== track.id)],
        index: 0,
    });
    load(true);
}

/**
 * Append tracks that are not queued yet and report how many were added.
 */
export function enqueue(tracks: PlayerTrack[]): number {
    const queued = new Set(state.queue.map((entry) => entry.id));
    const added = tracks.filter((track) => {
        if (queued.has(track.id)) {
            return false;
        }

        queued.add(track.id);

        return true;
    });

    if (added.length === 0) {
        return 0;
    }

    const wasEmpty = state.queue.length === 0;

    set({ queue: [...state.queue, ...added] });

    if (wasEmpty) {
        set({ index: 0 });
        load(false);
    } else {
        save();
    }

    return added.length;
}

/**
 * Replace the queue and start with its first track.
 */
export function playAll(tracks: PlayerTrack[]): void {
    if (tracks.length === 0) {
        return;
    }

    set({ queue: tracks, index: 0 });
    load(true);
}

export function resume(): void {
    const track = currentTrack();

    if (!track) {
        return;
    }

    if (loadedFileId !== track.fileId || state.error) {
        load(true);

        return;
    }

    start();
}

export function pause(): void {
    wantsToPlay = false;
    clearSkipTimer();
    audio?.pause();
}

export function toggle(): void {
    if (state.playing) {
        pause();
    } else {
        resume();
    }
}

export function next(): void {
    go(state.index + 1, wantsToPlay || state.playing);
}

/**
 * Back to the start of the track, or to the previous one when the track
 * has only just begun.
 */
export function previous(): void {
    if (state.index === 0 || (audio && audio.currentTime > 3)) {
        seek(0);

        return;
    }

    go(state.index - 1, wantsToPlay || state.playing);
}

export function jumpTo(index: number): void {
    go(index, true);
}

export function seek(position: number): void {
    if (!audio || loadedFileId === null) {
        return;
    }

    audio.currentTime = position;
    set({ position });
}

export function setVolume(volume: number): void {
    const clamped = Math.min(1, Math.max(0, volume));

    element().volume = clamped;
    element().muted = false;
    set({ volume: clamped, muted: false });
    save();
}

export function toggleMuted(): void {
    const muted = !state.muted;

    element().muted = muted;
    set({ muted });
    save();
}

export function remove(index: number): void {
    if (index < 0 || index >= state.queue.length) {
        return;
    }

    const queue = state.queue.filter((_, position) => position !== index);

    if (queue.length === 0) {
        clear();

        return;
    }

    if (index !== state.index) {
        set({
            queue,
            index: index < state.index ? state.index - 1 : state.index,
        });
        save();

        return;
    }

    const keepPlaying = wantsToPlay || state.playing;
    const wasLast = index >= queue.length;

    set({ queue, index: Math.min(index, queue.length - 1) });
    load(keepPlaying && !wasLast);
}

/**
 * Move a queue entry to another position. Whatever is playing keeps
 * playing: only its place in the queue changes.
 */
export function move(from: number, to: number): void {
    const last = state.queue.length - 1;

    if (from < 0 || from > last || to < 0 || to > last || from === to) {
        return;
    }

    const current = state.queue[state.index];
    const queue = [...state.queue];
    const [moved] = queue.splice(from, 1);
    queue.splice(to, 0, moved);

    set({ queue, index: Math.max(0, queue.indexOf(current)) });
    save();
}

export function clear(): void {
    unload();
    set({ ...EMPTY, volume: state.volume, muted: state.muted });
    save();
}

/**
 * Work out who the player belongs to on the page being shown. Audio is for
 * label admins only. Pages outside a label (account settings) do not say
 * which role the user has; there the player keeps or restores the queue
 * this user built as an admin of the same label, and nothing else.
 */
export function syncScopeFromPage(
    userId: number | null,
    label: { id: number; role: string | null } | null,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (userId === null || label === null || label.role === 'payee') {
        syncScope(null);

        return;
    }

    const known = scope ?? read();

    if (
        label.role === 'admin' ||
        (known?.labelId === label.id && known.userId === userId)
    ) {
        syncScope({ labelId: label.id, userId });
    } else if (scope !== null) {
        syncScope(null);
    }
}

if (typeof window !== 'undefined') {
    // Signing out or switching to a label where this user is not an admin
    // leads to pages without the player dock, so the queue is dropped here.
    router.on('navigate', (event) => {
        const { auth, label } = event.detail.page.props;

        syncScopeFromPage(auth?.user?.id ?? null, label ?? null);
    });
}
