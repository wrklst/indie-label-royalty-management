export type Origin = 'statement' | 'search' | 'manual';

/** How the label learned of a play, or of a link kept with it. */
export const ORIGINS: Record<Origin, string> = {
    statement: 'Neighbouring rights statement',
    search: 'Web search',
    manual: 'Entered by hand',
};
