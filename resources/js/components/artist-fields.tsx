import { Field, SelectInput } from '@/components/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types';

/** What the artist form edits, on the list and on the artist's own page. */
export type EditableArtist = {
    id: number;
    name: string;
    alias_of_id: number | null;
    spotify_id: string | null;
    discogs_search_name: string | null;
    notes: string | null;
};

export function ArtistFields({
    artist,
    options,
    errors,
}: {
    artist?: EditableArtist;
    options: Option[];
    errors: Record<string, string>;
}) {
    return (
        <>
            <Field label="Name" htmlFor="name" error={errors.name} required>
                <Input
                    id="name"
                    name="name"
                    defaultValue={artist?.name}
                    required
                />
            </Field>
            <Field
                label="Alias of"
                htmlFor="alias_of_id"
                error={errors.alias_of_id}
                hint="Set this when the artist is another name of an artist already in the catalog."
            >
                <SelectInput
                    id="alias_of_id"
                    name="alias_of_id"
                    defaultValue={artist?.alias_of_id ?? ''}
                    placeholder="Not an alias"
                    options={options.filter(
                        (option) => option.id !== artist?.id,
                    )}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Spotify ID"
                    htmlFor="spotify_id"
                    error={errors.spotify_id}
                >
                    <Input
                        id="spotify_id"
                        name="spotify_id"
                        defaultValue={artist?.spotify_id ?? ''}
                    />
                </Field>
                <Field
                    label="Discogs search name"
                    htmlFor="discogs_search_name"
                    error={errors.discogs_search_name}
                >
                    <Input
                        id="discogs_search_name"
                        name="discogs_search_name"
                        defaultValue={artist?.discogs_search_name ?? ''}
                    />
                </Field>
            </div>
            <Field label="Notes" htmlFor="notes" error={errors.notes}>
                <Textarea
                    id="notes"
                    name="notes"
                    defaultValue={artist?.notes ?? ''}
                />
            </Field>
        </>
    );
}
