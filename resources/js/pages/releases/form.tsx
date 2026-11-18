import { Link, useForm, usePage } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import ReleaseController from '@/actions/App/Http/Controllers/ReleaseController';
import { DeleteButton } from '@/components/delete-button';
import { CheckField, Field, SelectInput } from '@/components/field';
import InputError from '@/components/input-error';
import { Page, Panel } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index, show } from '@/routes/releases';
import type { Option } from '@/types';

type TrackRow = {
    id: number | null;
    name: string;
    artist_id: number | '';
    contract_id: number | '';
    isrc: string;
    iswc: string;
    track_number: number | '';
    duration: number | '';
};

type Release = {
    id: number;
    name: string;
    artist_id: number | null;
    contract_id: number | null;
    catalog_number: string | null;
    upc: string | null;
    released_on: string | null;
    spotify_id: string | null;
    discogs_id: string | null;
    accounts_mechanicals: boolean;
    mechanicals_rate: string | null;
    ppd_rate: string | null;
    reserves_rate: string | null;
    notes: string | null;
    tracks: {
        id: number;
        name: string;
        artist_id: number | null;
        contract_id: number | null;
        isrc: string | null;
        iswc: string | null;
        track_number: number | null;
        duration: number | null;
    }[];
};

const numberOrBlank = (value: string): number | '' =>
    value === '' ? '' : Number(value);

export default function ReleaseForm({
    release,
    artistOptions,
    contractOptions,
}: {
    release: Release | null;
    artistOptions: Option[];
    contractOptions: Option[];
}) {
    const { label } = usePage().props;
    const form = useForm({
        name: release?.name ?? '',
        artist_id: (release?.artist_id ?? '') as number | '',
        contract_id: (release?.contract_id ?? '') as number | '',
        catalog_number: release?.catalog_number ?? '',
        upc: release?.upc ?? '',
        released_on: release?.released_on ?? '',
        spotify_id: release?.spotify_id ?? '',
        discogs_id: release?.discogs_id ?? '',
        accounts_mechanicals: release?.accounts_mechanicals ?? false,
        mechanicals_rate: release?.mechanicals_rate ?? '',
        ppd_rate: release?.ppd_rate ?? '',
        reserves_rate: release?.reserves_rate ?? '',
        notes: release?.notes ?? '',
        tracks: (release?.tracks ?? []).map((track) => ({
            id: track.id,
            name: track.name,
            artist_id: track.artist_id ?? '',
            contract_id: track.contract_id ?? '',
            isrc: track.isrc ?? '',
            iswc: track.iswc ?? '',
            track_number: track.track_number ?? '',
            duration: track.duration ?? '',
        })) as TrackRow[],
    });

    const errors = form.errors as Record<string, string>;
    const removedExisting =
        (release?.tracks.length ?? 0) >
        form.data.tracks.filter((track) => track.id !== null).length;

    const setTrack = (position: number, changes: Partial<TrackRow>) =>
        form.setData(
            'tracks',
            form.data.tracks.map((track, current) =>
                current === position ? { ...track, ...changes } : track,
            ),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (release) {
            form.put(ReleaseController.update.url(release.id));
        } else {
            form.post(ReleaseController.store.url());
        }
    };

    return (
        <Page
            title={release ? `Edit ${release.name}` : 'New release'}
            actions={
                release && (
                    <DeleteButton
                        url={ReleaseController.destroy.url(release.id)}
                        label="Delete"
                        title={`Delete ${release.name}?`}
                        description="The release, its tracks and items are deleted. Its transactions are kept but become unassigned, so they stop earning for any payee."
                    />
                )
            }
        >
            <form onSubmit={submit} className="grid max-w-5xl gap-6">
                <Panel title="Release">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            label="Name"
                            htmlFor="name"
                            error={errors.name}
                            required
                            className="md:col-span-2"
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Artist"
                            htmlFor="artist_id"
                            error={errors.artist_id}
                        >
                            <SelectInput
                                id="artist_id"
                                value={form.data.artist_id}
                                onChange={(event) =>
                                    form.setData(
                                        'artist_id',
                                        numberOrBlank(event.target.value),
                                    )
                                }
                                options={artistOptions}
                                placeholder="No artist / various"
                            />
                        </Field>
                        <Field
                            label="Contract"
                            htmlFor="contract_id"
                            error={errors.contract_id}
                            hint="Applies to every track that has no contract of its own."
                        >
                            <SelectInput
                                id="contract_id"
                                value={form.data.contract_id}
                                onChange={(event) =>
                                    form.setData(
                                        'contract_id',
                                        numberOrBlank(event.target.value),
                                    )
                                }
                                options={contractOptions}
                                placeholder="No contract – label keeps all"
                            />
                        </Field>
                        <Field
                            label="Catalog number"
                            htmlFor="catalog_number"
                            error={errors.catalog_number}
                        >
                            <Input
                                id="catalog_number"
                                value={form.data.catalog_number}
                                onChange={(event) =>
                                    form.setData(
                                        'catalog_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="CAT015"
                            />
                        </Field>
                        <Field
                            label="UPC / EAN"
                            htmlFor="upc"
                            error={errors.upc}
                            hint="Imports find the release by this code."
                        >
                            <Input
                                id="upc"
                                inputMode="numeric"
                                value={form.data.upc}
                                onChange={(event) =>
                                    form.setData('upc', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Release date"
                            htmlFor="released_on"
                            error={errors.released_on}
                        >
                            <Input
                                id="released_on"
                                type="date"
                                value={form.data.released_on}
                                onChange={(event) =>
                                    form.setData(
                                        'released_on',
                                        event.target.value,
                                    )
                                }
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
                                    value={form.data.spotify_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'spotify_id',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Discogs ID"
                                htmlFor="discogs_id"
                                error={errors.discogs_id}
                            >
                                <Input
                                    id="discogs_id"
                                    value={form.data.discogs_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'discogs_id',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                        <Field
                            label="Notes"
                            htmlFor="notes"
                            error={errors.notes}
                            className="md:col-span-2"
                        >
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </Field>
                    </div>
                </Panel>

                <Panel
                    title="Tracks"
                    description="Give a track its own contract only when its deal differs from the release, e.g. a remix."
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('tracks', [
                                    ...form.data.tracks,
                                    {
                                        id: null,
                                        name: '',
                                        artist_id: form.data.artist_id,
                                        contract_id: '',
                                        isrc: '',
                                        iswc: '',
                                        track_number:
                                            form.data.tracks.length + 1,
                                        duration: '',
                                    },
                                ])
                            }
                        >
                            <Plus />
                            Add track
                        </Button>
                    }
                >
                    {form.data.tracks.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No tracks. Income can still be booked on the release
                            itself.
                        </p>
                    ) : (
                        <div className="grid gap-3">
                            <div className="hidden grid-cols-[3.5rem_1.6fr_1fr_9rem_1.2fr_2.25rem] gap-2 text-xs font-medium text-muted-foreground lg:grid">
                                <span>#</span>
                                <span>Title</span>
                                <span>Artist</span>
                                <span>ISRC</span>
                                <span>Contract</span>
                                <span />
                            </div>
                            {form.data.tracks.map((track, position) => (
                                <div
                                    key={track.id ?? `new-${position}`}
                                    className="grid gap-2 lg:grid-cols-[3.5rem_1.6fr_1fr_9rem_1.2fr_2.25rem]"
                                >
                                    <Input
                                        aria-label="Track number"
                                        type="number"
                                        min="1"
                                        value={track.track_number}
                                        onChange={(event) =>
                                            setTrack(position, {
                                                track_number: numberOrBlank(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                    />
                                    <div>
                                        <Input
                                            aria-label="Title"
                                            value={track.name}
                                            onChange={(event) =>
                                                setTrack(position, {
                                                    name: event.target.value,
                                                })
                                            }
                                            placeholder="Track title"
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `tracks.${position}.name`
                                                ]
                                            }
                                        />
                                    </div>
                                    <SelectInput
                                        aria-label="Artist"
                                        value={track.artist_id}
                                        onChange={(event) =>
                                            setTrack(position, {
                                                artist_id: numberOrBlank(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                        options={artistOptions}
                                        placeholder="Artist…"
                                    />
                                    <div>
                                        <Input
                                            aria-label="ISRC"
                                            value={track.isrc}
                                            onChange={(event) =>
                                                setTrack(position, {
                                                    isrc: event.target.value,
                                                })
                                            }
                                            placeholder="DEXXX2600001"
                                            className="font-mono"
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `tracks.${position}.isrc`
                                                ]
                                            }
                                        />
                                    </div>
                                    <SelectInput
                                        aria-label="Contract"
                                        value={track.contract_id}
                                        onChange={(event) =>
                                            setTrack(position, {
                                                contract_id: numberOrBlank(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                        options={contractOptions}
                                        placeholder="Same as release"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remove track"
                                        onClick={() =>
                                            form.setData(
                                                'tracks',
                                                form.data.tracks.filter(
                                                    (_, current) =>
                                                        current !== position,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                    {removedExisting && (
                        <p className="mt-3 text-sm text-amber-700 dark:text-amber-400">
                            Saving deletes the removed tracks. Their
                            transactions stay on the release but lose the track
                            and any track-level contract.
                        </p>
                    )}
                </Panel>

                {label?.role === 'admin' && (
                    <Panel
                        title="Mechanicals, PPD and reserves"
                        description="Stored with the release for your records. These rates are not applied to statements yet."
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field
                                label="Mechanicals rate"
                                htmlFor="mechanicals_rate"
                                error={errors.mechanicals_rate}
                            >
                                <Input
                                    id="mechanicals_rate"
                                    type="number"
                                    step="any"
                                    min="0"
                                    value={form.data.mechanicals_rate}
                                    onChange={(event) =>
                                        form.setData(
                                            'mechanicals_rate',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="PPD"
                                htmlFor="ppd_rate"
                                error={errors.ppd_rate}
                            >
                                <Input
                                    id="ppd_rate"
                                    type="number"
                                    step="any"
                                    min="0"
                                    value={form.data.ppd_rate}
                                    onChange={(event) =>
                                        form.setData(
                                            'ppd_rate',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Reserves %"
                                htmlFor="reserves_rate"
                                error={errors.reserves_rate}
                            >
                                <Input
                                    id="reserves_rate"
                                    type="number"
                                    step="any"
                                    min="0"
                                    max="100"
                                    value={form.data.reserves_rate}
                                    onChange={(event) =>
                                        form.setData(
                                            'reserves_rate',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <CheckField
                                label="Account for mechanicals"
                                checked={form.data.accounts_mechanicals}
                                onChange={(event) =>
                                    form.setData(
                                        'accounts_mechanicals',
                                        event.target.checked,
                                    )
                                }
                                className="md:col-span-3"
                            />
                        </div>
                    </Panel>
                )}

                <div className="flex items-center gap-3">
                    <Button disabled={form.processing}>
                        {release ? 'Save release' : 'Create release'}
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={release ? show(release.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </Page>
    );
}

ReleaseForm.layout = {
    breadcrumbs: [
        { title: 'Releases', href: index() },
        { title: 'Release', href: '#' },
    ],
};
