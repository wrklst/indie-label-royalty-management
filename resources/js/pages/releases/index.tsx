import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Amount } from '@/components/amount';
import { CheckField, SelectInput } from '@/components/field';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { date } from '@/lib/format';
import { edit as editContract } from '@/routes/contracts';
import { create as createImport } from '@/routes/data-imports';
import { create, index, show } from '@/routes/releases';
import type { Option, Paginated } from '@/types';

type Release = {
    id: number;
    name: string;
    catalog_number: string | null;
    upc: string | null;
    tracks_count: number;
    released_on: string | null;
    artist: string | null;
    contract: Option | null;
    income: string;
};

type Filters = {
    search: string;
    artist: number | null;
    without_contract: boolean;
};

export default function Releases({
    releases,
    filters,
    artistOptions,
}: {
    releases: Paginated<Release>;
    filters: Filters;
    artistOptions: Option[];
}) {
    const { apply } = useFilters(index.url(), filters);
    const filtered =
        filters.search !== '' ||
        filters.artist !== null ||
        filters.without_contract;

    return (
        <Page
            title="Releases"
            description="Albums, EPs and singles. Releases are created from your imports, matched by UPC/EAN and catalog number."
            actions={
                <Button asChild>
                    <Link href={create()}>
                        <Plus />
                        New release
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-wrap items-center gap-3">
                <Input
                    type="search"
                    placeholder="Search name, catalog number or UPC…"
                    defaultValue={filters.search}
                    onChange={(event) =>
                        apply({ search: event.target.value }, true)
                    }
                    className="max-w-sm"
                />
                <SelectInput
                    aria-label="Artist"
                    value={filters.artist ?? ''}
                    onChange={(event) =>
                        apply({
                            artist: event.target.value
                                ? Number(event.target.value)
                                : null,
                        })
                    }
                    options={artistOptions}
                    placeholder="All artists"
                    className="w-52"
                />
                <CheckField
                    label="Without contract"
                    checked={filters.without_contract}
                    onChange={(event) =>
                        apply({ without_contract: event.target.checked })
                    }
                />
            </div>

            {releases.total === 0 ? (
                <EmptyState
                    title={filtered ? 'No release matches' : 'No releases yet'}
                    description={
                        filtered
                            ? undefined
                            : 'Import a distributor statement and the releases and tracks in it are created for you.'
                    }
                    action={
                        !filtered ? (
                            <Button asChild>
                                <Link href={createImport()}>
                                    Import a statement
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Cat. no.</TableHead>
                                <TableHead>Release</TableHead>
                                <TableHead>Artist</TableHead>
                                <TableHead>UPC / EAN</TableHead>
                                <TableHead>Released</TableHead>
                                <TableHead className="text-right">
                                    Tracks
                                </TableHead>
                                <TableHead>Contract</TableHead>
                                <TableHead className="text-right">
                                    Income to date
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {releases.data.map((release) => (
                                <TableRow key={release.id}>
                                    <TableCell className="font-mono text-xs">
                                        {release.catalog_number ?? '–'}
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            href={show(release.id)}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {release.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {release.artist ?? '–'}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                        {release.upc ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {date(release.released_on)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {release.tracks_count}
                                    </TableCell>
                                    <TableCell>
                                        {release.contract ? (
                                            <Link
                                                href={editContract(
                                                    release.contract.id,
                                                )}
                                                className="underline decoration-muted-foreground/40 underline-offset-4"
                                            >
                                                {release.contract.name}
                                            </Link>
                                        ) : (
                                            <Badge variant="outline">
                                                none
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={release.income} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={releases} noun="releases" />
                </Panel>
            )}
        </Page>
    );
}

Releases.layout = { breadcrumbs: [{ title: 'Releases', href: index() }] };
