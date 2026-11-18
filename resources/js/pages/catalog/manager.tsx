import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import CatalogController from '@/actions/App/Http/Controllers/CatalogController';
import { SelectInput } from '@/components/field';
import { EmptyState, Page, Panel } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/catalog';
import { create as createContract } from '@/routes/contracts';
import { create as createImport } from '@/routes/data-imports';
import { show as showRelease } from '@/routes/releases';
import type { Option } from '@/types';

type Track = {
    id: number;
    name: string;
    isrc: string | null;
    contract_id: number | null;
    artist: string | null;
};
type Release = {
    id: number;
    name: string;
    catalog_number: string | null;
    contract_id: number | null;
    artist: string | null;
    tracks: Track[];
};

function assign(type: 'release' | 'track', id: number, value: string) {
    router.patch(
        CatalogController.assign.url(),
        { type, id, contract_id: value === '' ? null : Number(value) },
        { preserveScroll: true, preserveState: true },
    );
}

export default function CatalogManager({
    releases,
    contractOptions,
}: {
    releases: Release[];
    contractOptions: Option[];
}) {
    const [search, setSearch] = useState('');
    const [onlyMissing, setOnlyMissing] = useState(false);
    const contractName = (id: number | null) =>
        contractOptions.find((option) => option.id === id)?.name;

    const needle = search.trim().toLowerCase();
    const visible = releases.filter((release) => {
        const matches =
            needle === '' ||
            [
                release.name,
                release.catalog_number,
                release.artist,
                ...release.tracks.flatMap((track) => [
                    track.name,
                    track.isrc,
                    track.artist,
                ]),
            ].some((value) => value?.toLowerCase().includes(needle));
        const missing =
            release.contract_id === null &&
            (release.tracks.length === 0 ||
                release.tracks.some((track) => track.contract_id === null));

        return matches && (!onlyMissing || missing);
    });
    const uncovered = releases.reduce(
        (count, release) =>
            count +
            (release.contract_id === null
                ? release.tracks.filter((track) => track.contract_id === null)
                      .length
                : 0),
        0,
    );

    return (
        <Page
            title="Catalog manager"
            description="Assign contracts across the whole catalog in one place. A track uses its own contract if it has one, otherwise the contract of its release. Changes save immediately."
            actions={
                <Button variant="outline" asChild>
                    <Link href={createContract()}>New contract</Link>
                </Button>
            }
        >
            {releases.length === 0 ? (
                <EmptyState
                    title="Your catalog is empty"
                    description="Import a distributor statement to build it automatically."
                    action={
                        <Button asChild>
                            <Link href={createImport()}>
                                Import a statement
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <>
                    <div className="flex flex-wrap items-center gap-3">
                        <Input
                            type="search"
                            placeholder="Filter releases and tracks…"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="max-w-sm"
                        />
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="size-4 accent-primary"
                                checked={onlyMissing}
                                onChange={(event) =>
                                    setOnlyMissing(event.target.checked)
                                }
                            />
                            Only releases with tracks lacking a contract
                        </label>
                        <span className="ml-auto text-sm text-muted-foreground">
                            {uncovered === 0
                                ? 'Every track is covered by a contract.'
                                : `${uncovered} track${uncovered === 1 ? '' : 's'} without a contract`}
                        </span>
                    </div>

                    <div className="grid gap-4">
                        {visible.map((release) => (
                            <Panel
                                key={release.id}
                                flush
                                title={
                                    <span className="flex flex-wrap items-center gap-2">
                                        {release.catalog_number && (
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {release.catalog_number}
                                            </span>
                                        )}
                                        <Link
                                            href={showRelease(release.id)}
                                            className="underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {release.name}
                                        </Link>
                                        <span className="font-normal text-muted-foreground">
                                            {release.artist}
                                        </span>
                                    </span>
                                }
                                actions={
                                    <SelectInput
                                        aria-label={`Contract for ${release.name}`}
                                        value={release.contract_id ?? ''}
                                        onChange={(event) =>
                                            assign(
                                                'release',
                                                release.id,
                                                event.target.value,
                                            )
                                        }
                                        options={contractOptions}
                                        placeholder="No release contract"
                                        className="w-72"
                                    />
                                }
                            >
                                {release.tracks.length === 0 ? (
                                    <p className="px-4 py-3 text-sm text-muted-foreground">
                                        No tracks.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {release.tracks.map((track) => (
                                            <li
                                                key={track.id}
                                                className="flex flex-wrap items-center gap-3 px-4 py-2 text-sm"
                                            >
                                                <span className="min-w-0 flex-1">
                                                    <span className="font-medium">
                                                        {track.name}
                                                    </span>
                                                    {track.artist &&
                                                        track.artist !==
                                                            release.artist && (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                · {track.artist}
                                                            </span>
                                                        )}
                                                    {track.isrc && (
                                                        <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                            {track.isrc}
                                                        </span>
                                                    )}
                                                </span>
                                                {track.contract_id === null &&
                                                    release.contract_id ===
                                                        null && (
                                                        <Badge variant="destructive">
                                                            no contract
                                                        </Badge>
                                                    )}
                                                <SelectInput
                                                    aria-label={`Contract for ${track.name}`}
                                                    value={
                                                        track.contract_id ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        assign(
                                                            'track',
                                                            track.id,
                                                            event.target.value,
                                                        )
                                                    }
                                                    options={contractOptions}
                                                    placeholder={
                                                        release.contract_id
                                                            ? `Release: ${contractName(release.contract_id)}`
                                                            : 'No contract'
                                                    }
                                                    className="w-72"
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        ))}
                        {visible.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nothing matches the filter.
                            </p>
                        )}
                    </div>
                </>
            )}
        </Page>
    );
}

CatalogManager.layout = {
    breadcrumbs: [{ title: 'Catalog manager', href: index() }],
};
