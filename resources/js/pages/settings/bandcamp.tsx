import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import BandcampController from '@/actions/App/Http/Controllers/Settings/BandcampController';
import AlertError from '@/components/alert-error';
import { ConnectionCard } from '@/components/connection-card';
import { CheckField, Field, SelectInput } from '@/components/field';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit } from '@/routes/bandcamp';
import { index as dataImports } from '@/routes/data-imports';
import { dateTime } from '@/lib/format';

type Account = { band_id: number; name: string; subdomain: string | null };
type Band = Account & { member_bands: Account[] };

type Connection = {
    client_id_hint: string;
    bands: Band[];
    band_id: number | null;
    member_band_id: number | null;
    enabled: boolean;
    sync_from: string | null;
    last_synced_until: string | null;
    last_sync_at: string | null;
    last_sync_status: 'ok' | 'failed' | null;
    last_sync_message: string | null;
};

function accountName(account: Account): string {
    return account.subdomain
        ? `${account.name} (${account.subdomain}.bandcamp.com)`
        : account.name;
}

function BandcampError({ message }: { message?: string }) {
    return message ? <AlertError title="Bandcamp" errors={[message]} /> : null;
}

function Credentials() {
    return (
        <Form
            {...BandcampController.connect.form()}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <BandcampError message={errors.bandcamp} />
                    <Field
                        label="Client ID"
                        htmlFor="client_id"
                        error={errors.client_id}
                        required
                    >
                        <Input
                            id="client_id"
                            name="client_id"
                            autoComplete="off"
                            required
                        />
                    </Field>
                    <Field
                        label="Client secret"
                        htmlFor="client_secret"
                        error={errors.client_secret}
                        required
                        hint="Stored encrypted and never shown again."
                    >
                        <Input
                            id="client_secret"
                            name="client_secret"
                            type="password"
                            autoComplete="off"
                            required
                        />
                    </Field>
                    <div>
                        <Button disabled={processing}>
                            {processing
                                ? 'Asking Bandcamp…'
                                : 'Connect and load my bands'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function SyncSettings({ connection }: { connection: Connection }) {
    const [bandId, setBandId] = useState(connection.band_id ?? '');
    const members =
        connection.bands.find((band) => band.band_id === Number(bandId))
            ?.member_bands ?? [];

    return (
        <Form
            {...BandcampController.update.form()}
            options={{ preserveScroll: true }}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <Field
                        label="Bandcamp account"
                        htmlFor="band_id"
                        error={errors.band_id}
                        required
                        hint="The label or artist account whose sales are imported."
                    >
                        <SelectInput
                            id="band_id"
                            name="band_id"
                            value={bandId}
                            onChange={(event) => setBandId(event.target.value)}
                            required
                        >
                            <option value="">Choose account…</option>
                            {connection.bands.map((band) => (
                                <option key={band.band_id} value={band.band_id}>
                                    {accountName(band)}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    {members.length > 0 && (
                        <Field
                            label="Only this artist"
                            htmlFor="member_band_id"
                            error={errors.member_band_id}
                            hint="Leave empty to import the sales of every artist on the account."
                        >
                            <SelectInput
                                id="member_band_id"
                                name="member_band_id"
                                key={bandId}
                                defaultValue={connection.member_band_id ?? ''}
                            >
                                <option value="">All artists</option>
                                {members.map((member) => (
                                    <option
                                        key={member.band_id}
                                        value={member.band_id}
                                    >
                                        {accountName(member)}
                                    </option>
                                ))}
                            </SelectInput>
                        </Field>
                    )}
                    <Field
                        label="Import sales from"
                        htmlFor="sync_from"
                        error={errors.sync_from}
                        required
                        hint="The first sync fetches everything since this day. Sales that are in already, from an uploaded Bandcamp report or carried over from another royalty system, are not imported twice."
                    >
                        <Input
                            id="sync_from"
                            name="sync_from"
                            type="date"
                            className="w-44"
                            defaultValue={connection.sync_from ?? ''}
                            required
                        />
                    </Field>
                    <input type="hidden" name="enabled" value="0" />
                    <CheckField
                        name="enabled"
                        value="1"
                        label="Sync automatically every week"
                        hint="New sales are fetched every Monday morning."
                        defaultChecked={connection.enabled}
                    />
                    <div>
                        <Button disabled={processing}>Save</Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function SyncStatus({ connection }: { connection: Connection }) {
    return (
        <div className="space-y-4">
            {connection.last_sync_at ? (
                <div className="rounded-lg border p-3 text-sm">
                    <p className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                connection.last_sync_status === 'failed'
                                    ? 'destructive'
                                    : 'success'
                            }
                        >
                            {connection.last_sync_status === 'failed'
                                ? 'Failed'
                                : 'OK'}
                        </Badge>
                        <span>
                            Last sync {dateTime(connection.last_sync_at)}
                        </span>
                    </p>
                    {connection.last_sync_message && (
                        <p className="mt-2 text-muted-foreground">
                            {connection.last_sync_message}
                        </p>
                    )}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    No sync has run yet.
                </p>
            )}

            <Form
                {...BandcampController.sync.form()}
                options={{ preserveScroll: true }}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <BandcampError message={errors.bandcamp} />
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                variant="outline"
                                disabled={
                                    processing ||
                                    connection.band_id === null ||
                                    connection.sync_from === null
                                }
                            >
                                {processing ? 'Syncing…' : 'Sync now'}
                            </Button>
                            <Link
                                href={dataImports()}
                                className="text-sm underline underline-offset-4"
                            >
                                View imports
                            </Link>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {connection.band_id === null ||
                            connection.sync_from === null
                                ? 'Choose and save an account and a start date first.'
                                : 'Each month with new sales becomes one import. A first sync over several years fetches a few months per click: sync again until it reports that everything is in.'}
                        </p>
                    </>
                )}
            </Form>
        </div>
    );
}

export default function Bandcamp({
    connection,
}: {
    connection: Connection | null;
}) {
    return (
        <>
            <Head title="Bandcamp" />
            <h1 className="sr-only">Bandcamp</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Bandcamp"
                    description="Import your Bandcamp sales automatically instead of uploading sales reports."
                />

                {connection ? (
                    <ConnectionCard
                        title="Bandcamp"
                        facts={[
                            ['Client ID', connection.client_id_hint],
                            ['Client secret', 'stored, not shown'],
                        ]}
                        disconnect={BandcampController.destroy.url()}
                        disconnectLabel="Disconnect Bandcamp"
                        confirm="Disconnect Bandcamp? The stored client ID, secret and tokens are deleted and the weekly sync stops. Imported sales stay. To connect again you need your client ID and secret, and you may have to regenerate the client on Bandcamp."
                    />
                ) : (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Bandcamp grants API access on request: write to them
                            through{' '}
                            <a
                                href="https://bandcamp.com/contact?subj=API%20Access"
                                target="_blank"
                                rel="noreferrer"
                                className="underline underline-offset-4"
                            >
                                their contact form
                            </a>{' '}
                            and describe briefly that you want to read your
                            sales report. Once they have set it up, your client
                            ID and client secret appear on Bandcamp under
                            Settings → API Access.
                        </p>

                        <Credentials />
                    </>
                )}
            </div>

            {connection && (
                <>
                    <div className="mt-10 space-y-6">
                        <Heading
                            variant="small"
                            title="What to import"
                            description="Connected. Choose the account and where to start."
                        />
                        <SyncSettings
                            key={`${connection.band_id}-${connection.bands.length}`}
                            connection={connection}
                        />
                        <Form
                            {...BandcampController.bands.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <BandcampError message={errors.bandcamp} />
                                    <div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Asking Bandcamp…'
                                                : 'Load accounts from Bandcamp again'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>

                    <div className="mt-10 space-y-6">
                        <Heading
                            variant="small"
                            title="Sync"
                            description={
                                connection.last_synced_until
                                    ? `Sales up to ${dateTime(connection.last_synced_until)} are imported`
                                    : 'Nothing has been fetched yet'
                            }
                        />
                        <SyncStatus connection={connection} />
                    </div>
                </>
            )}
        </>
    );
}

Bandcamp.layout = {
    breadcrumbs: [{ title: 'Bandcamp', href: edit() }],
};
