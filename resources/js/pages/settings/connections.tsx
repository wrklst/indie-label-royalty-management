import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import ConnectionsController from '@/actions/App/Http/Controllers/Settings/ConnectionsController';
import { CheckField, Field, SelectInput } from '@/components/field';
import { Page } from '@/components/page';
import { ServiceCard } from '@/components/service-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { date, dateTime, money } from '@/lib/format';
import { edit as editBandcamp } from '@/routes/bandcamp';
import { edit } from '@/routes/connections';

type Service = {
    value: string;
    name: string;
    /** What the service needs besides a key, e.g. "Product ID"; null when nothing. */
    endpoint: string | null;
    /** The model used when none is named; null when one has to be named. */
    model: string | null;
};

type Model = {
    id: string;
    name: string;
    description: string | null;
    /** US dollars per million tokens. */
    input_price: number;
    output_price: number;
    /** US dollars for about a hundred receipts. */
    per_hundred: number;
    fixed_format: boolean;
    reasoning: boolean;
    released: string | null;
};

const CONNECTED = { label: 'Connected', variant: 'success' } as const;
const NOT_CONNECTED = { label: 'Not connected', variant: 'warning' } as const;

type Props = {
    services: {
        key: string;
        name: string;
        kind: string;
        purpose: string;
        gates: string;
        enabled: boolean;
    }[];
    zebralution: {
        number: string | null;
        origin_country: string | null;
        price_group: number;
        work_rights: string;
        work_rights_options: string[];
    };
    search: {
        connected: boolean;
        login: string | null;
        from_environment: boolean;
        airplay_markets: string[];
    };
    ai: {
        connected: boolean;
        provider: string | null;
        service: string | null;
        key_hint: string | null;
        endpoint_label: string | null;
        endpoint: string | null;
        model: string | null;
        trial: { at: string; seconds: number; missed: string[] } | null;
        services: Service[];
        catalogue: Record<string, Model[]>;
    };
    bandcamp: {
        connected: boolean;
        enabled: boolean;
        last_sync_at: string | null;
        disable_fee: boolean;
        exclude_physical: boolean;
    };
    storage: {
        disk: string;
        from_environment: boolean;
        bucket: string | null;
        region: string | null;
        endpoint: string | null;
        key_hint: string | null;
        direct_uploads: boolean | null;
        origin: string;
    } | null;
};

const dollars = (amount: number) => money(amount, 'USD');

function WebSearch({ search }: { search: Props['search'] }) {
    // A login from the environment is in use until an own one is asked for.
    const [open, setOpen] = useState(!search.from_environment);

    return (
        <ServiceCard
            title="Web search"
            kind="Login"
            description="The airplay research looks tracks up on Google through DataForSEO. Every search is billed to your DataForSEO account."
            status={
                search.connected
                    ? CONNECTED
                    : search.from_environment
                      ? { label: 'Set on the server', variant: 'success' }
                      : NOT_CONNECTED
            }
            facts={
                search.connected
                    ? [
                          ['API login', search.login],
                          ['API password', 'stored, not shown'],
                      ]
                    : search.from_environment
                      ? [['Login', 'set on the server']]
                      : []
            }
            note={
                !search.connected && search.from_environment
                    ? "This is the connection from the server's environment file. It stays in use until you connect your own login here."
                    : undefined
            }
            disconnect={
                search.connected
                    ? ConnectionsController.destroySearch.url()
                    : undefined
            }
            disconnectLabel="Disconnect DataForSEO"
            confirm="The stored login is deleted. To connect again you enter it anew."
        >
            {!search.connected && search.from_environment && !open && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen(true)}
                >
                    Connect a different login
                </Button>
            )}
            {!search.connected && open && (
                <Form
                    {...ConnectionsController.search.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="DataForSEO API login"
                                htmlFor="search_login"
                                error={errors.login}
                                hint="In your DataForSEO dashboard under API Access."
                                required
                            >
                                <Input
                                    id="search_login"
                                    name="login"
                                    autoComplete="off"
                                    required
                                />
                            </Field>
                            <Field
                                label="DataForSEO API password"
                                htmlFor="search_password"
                                error={errors.password}
                                hint="The API password shown there, not the one you sign in to their website with."
                                required
                            >
                                <Input
                                    id="search_password"
                                    name="password"
                                    type="password"
                                    autoComplete="new-password"
                                    required
                                />
                            </Field>
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Testing the login…'
                                        : 'Test and connect'}
                                </Button>
                                {search.from_environment && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            )}
            <Form
                {...ConnectionsController.markets.form()}
                options={{ preserveScroll: true }}
                className="grid gap-3 border-t pt-4"
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="Airplay research countries"
                            htmlFor="airplay_markets"
                            error={
                                errors.airplay_markets ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('airplay_markets.'),
                                )?.[1]
                            }
                            hint="Two-letter country codes separated by spaces or commas. Every track is searched once per country, and each search is billed."
                            required
                        >
                            <Input
                                id="airplay_markets"
                                name="airplay_markets"
                                defaultValue={search.airplay_markets.join(' ')}
                                placeholder="DE GB US"
                                className="font-mono uppercase"
                                required
                            />
                        </Field>
                        <div>
                            <Button variant="outline" disabled={processing}>
                                Save countries
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </ServiceCard>
    );
}

/**
 * The models of a service that can read a receipt, with what they cost
 * and what to expect, or a free field when the catalogue knows none.
 */
function ModelPicker({
    service,
    models,
    error,
    current,
}: {
    service: Service;
    models: Model[];
    error?: string;
    /** The model in use, when one of a connected service is being changed. */
    current?: string | null;
}) {
    // The cheapest model that answers in a fixed format is the sensible start.
    const recommended =
        models.find((model) => model.fixed_format) ?? models[0] ?? null;
    const [chosen, setChosen] = useState(
        current
            ? models.some((model) => model.id === current)
                ? current
                : 'other'
            : (recommended?.id ?? 'other'),
    );
    const model = models.find((entry) => entry.id === chosen);

    if (models.length === 0) {
        return (
            <Field
                label="Model"
                htmlFor="ai_model"
                error={error}
                hint={
                    service.model
                        ? `Leave empty to use ${service.model}.`
                        : 'The exact name of a model that understands images, as the service writes it.'
                }
                required={!service.model}
            >
                <Input
                    id="ai_model"
                    name="model"
                    autoComplete="off"
                    required={!service.model}
                />
            </Field>
        );
    }

    return (
        <>
            <Field
                label="Model"
                htmlFor="ai_model"
                error={error}
                hint="Only models that take pictures are listed, cheapest first. Prices are the public list prices in US dollars; a receipt is counted as about 3,000 tokens in and 150 out."
                required
            >
                <SelectInput
                    id="ai_model"
                    name={chosen === 'other' ? undefined : 'model'}
                    value={chosen}
                    onChange={(event) => setChosen(event.target.value)}
                >
                    {models.map((entry) => (
                        <option key={entry.id} value={entry.id}>
                            {entry.name} · about {dollars(entry.per_hundred)}{' '}
                            per 100 receipts
                            {entry.id === recommended?.id
                                ? ' · recommended'
                                : ''}
                        </option>
                    ))}
                    <option value="other">Another model…</option>
                </SelectInput>
            </Field>
            {model && (
                <div
                    data-model-details
                    className="space-y-1 rounded-lg border bg-muted/40 p-3 text-sm"
                >
                    <p className="font-medium">{model.name}</p>
                    {model.description && <p>{model.description}.</p>}
                    <ul className="list-disc space-y-0.5 ps-5 text-muted-foreground">
                        <li>
                            {dollars(model.input_price)} per million tokens in,{' '}
                            {dollars(model.output_price)} out: about{' '}
                            {dollars(model.per_hundred)} per 100 receipts.
                        </li>
                        <li>
                            {model.fixed_format
                                ? 'Answers in a fixed format, so the fields arrive reliably.'
                                : 'A fixed answer format is not confirmed for it: now and then a receipt may have to be read again.'}
                        </li>
                        <li>
                            {model.reasoning
                                ? 'Works a document through before answering: slower, more careful with unclear receipts.'
                                : 'Answers directly: fast, best on clean invoices.'}
                        </li>
                        {model.released && (
                            <li>Released {date(model.released)}.</li>
                        )}
                    </ul>
                    <p className="text-xs text-muted-foreground">
                        These are the catalog&apos;s figures. Connecting has the
                        model read a sample receipt and tells you how it did and
                        how long it took.
                    </p>
                </div>
            )}
            {chosen === 'other' && (
                <Field
                    label="Model name"
                    htmlFor="ai_model_other"
                    hint="Exactly as the service writes it."
                    required
                >
                    <Input
                        id="ai_model_other"
                        name="model"
                        autoComplete="off"
                        required
                    />
                </Field>
            )}
        </>
    );
}

/**
 * Another model for the connected service, without entering the key
 * again. The new model is tested before it replaces the one in use.
 */
function ChangeModel({ ai }: { ai: Props['ai'] }) {
    const [open, setOpen] = useState(false);
    const service = ai.services.find((entry) => entry.value === ai.provider);

    if (!service) {
        return null;
    }

    if (!open) {
        return (
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
            >
                Change model
            </Button>
        );
    }

    return (
        <Form
            {...ConnectionsController.aiModel.form()}
            options={{ preserveScroll: true }}
            onSuccess={() => setOpen(false)}
            className="grid gap-4 border-t pt-4"
        >
            {({ errors, processing }) => (
                <>
                    <ModelPicker
                        service={service}
                        models={ai.catalogue[service.value] ?? []}
                        error={errors.model}
                        current={ai.model}
                    />
                    <div className="flex gap-2">
                        <Button disabled={processing}>
                            {processing
                                ? 'Reading a sample receipt…'
                                : 'Test and switch'}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function ReceiptReading({ ai }: { ai: Props['ai'] }) {
    const [provider, setProvider] = useState('');
    const service = ai.services.find((entry) => entry.value === provider);

    return (
        <ServiceCard
            title="Receipt reading"
            kind="Login"
            description="An AI service reads vendor, date, amount and category off receipts you upload under Overheads. The receipts are sent to the service you choose and billed to your account there. Without a service, receipts are stored and booked by hand."
            status={ai.connected ? CONNECTED : NOT_CONNECTED}
            facts={
                ai.connected
                    ? [
                          ['Service', ai.service ?? 'AI service'],
                          ['Model', ai.model ?? '–'],
                          ...(ai.endpoint_label
                              ? ([[ai.endpoint_label, ai.endpoint ?? '–']] as [
                                    string,
                                    ReactNode,
                                ][])
                              : []),
                          ['API key', ai.key_hint ?? 'stored'],
                      ]
                    : []
            }
            note={
                ai.connected && ai.trial
                    ? ai.trial.missed.length === 0
                        ? `Tested ${date(ai.trial.at)}: read the sample receipt correctly in ${ai.trial.seconds} seconds.`
                        : `Tested ${date(ai.trial.at)}: got ${ai.trial.missed.join(', ')} wrong on the sample receipt (${ai.trial.seconds} seconds). Another model may read better.`
                    : undefined
            }
            disconnect={
                ai.connected ? ConnectionsController.destroyAi.url() : undefined
            }
            disconnectLabel={`Disconnect ${ai.service ?? 'the AI service'}`}
            confirm="The stored key is deleted and receipts are no longer read. To connect again you enter the key anew."
        >
            {ai.connected ? (
                <ChangeModel ai={ai} />
            ) : (
                <Form
                    {...ConnectionsController.ai.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="AI service"
                                htmlFor="ai_provider"
                                error={errors.provider}
                                required
                            >
                                <SelectInput
                                    id="ai_provider"
                                    name="provider"
                                    value={provider}
                                    onChange={(event) =>
                                        setProvider(event.target.value)
                                    }
                                    required
                                >
                                    <option value="">Choose a service…</option>
                                    {ai.services.map((entry) => (
                                        <option
                                            key={entry.value}
                                            value={entry.value}
                                        >
                                            {entry.name}
                                        </option>
                                    ))}
                                </SelectInput>
                            </Field>
                            {service && (
                                <>
                                    <Field
                                        label="API key"
                                        htmlFor="ai_key"
                                        error={errors.key}
                                        hint={
                                            provider === 'infomaniak'
                                                ? 'An API token from the Infomaniak Manager with access to AI Tools.'
                                                : undefined
                                        }
                                        required
                                    >
                                        <Input
                                            id="ai_key"
                                            name="key"
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                    </Field>
                                    {service.endpoint && (
                                        <Field
                                            label={service.endpoint}
                                            htmlFor="ai_endpoint"
                                            error={errors.endpoint}
                                            hint={
                                                provider === 'infomaniak'
                                                    ? 'The number of your AI Tools product, shown in the Infomaniak Manager under AI Tools.'
                                                    : 'The address the service answers at, up to and without /chat/completions.'
                                            }
                                            required
                                        >
                                            <Input
                                                id="ai_endpoint"
                                                name="endpoint"
                                                autoComplete="off"
                                                required
                                            />
                                        </Field>
                                    )}
                                    <ModelPicker
                                        key={provider}
                                        service={service}
                                        models={ai.catalogue[provider] ?? []}
                                        error={errors.model}
                                    />
                                    <div>
                                        <Button disabled={processing}>
                                            {processing
                                                ? 'Reading a sample receipt…'
                                                : 'Test and connect'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </>
                    )}
                </Form>
            )}
        </ServiceCard>
    );
}

function Storage({ storage }: { storage: NonNullable<Props['storage']> }) {
    const [disk, setDisk] = useState('s3');
    // The environment's storage is in use until another one is asked for.
    const [open, setOpen] = useState(false);

    return (
        <ServiceCard
            title="File storage"
            kind="Installation"
            description="Where release files, receipts and statement files of every label on this installation are stored. Only you, as the installation's admin, see this card."
            status={CONNECTED}
            facts={
                storage.disk === 's3'
                    ? [
                          ['Storage', 'S3-compatible bucket'],
                          ['Bucket', storage.bucket ?? '–'],
                          ['Endpoint', storage.endpoint ?? 'Amazon S3'],
                          ['Region', storage.region ?? '–'],
                          ['Access key', storage.key_hint ?? 'stored'],
                      ]
                    : [
                          ['Storage', 'This server'],
                          ['New files', 'are stored on this server'],
                      ]
            }
            note={
                storage.from_environment
                    ? "This is the connection from the server's environment file. It stays in use until you connect a different storage here."
                    : 'Applies to new files. Files stored before stay where they are; move them with php artisan royalties:move-files.'
            }
            disconnect={
                storage.from_environment
                    ? undefined
                    : ConnectionsController.destroyStorage.url()
            }
            disconnectLabel="Disconnect this file storage"
            confirm="The server's environment file decides again where new files go. Files already stored are not touched, but files in this bucket are unreachable while another storage is in use."
        >
            {storage.from_environment && !open && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen(true)}
                >
                    Connect a different storage
                </Button>
            )}
            {storage.direct_uploads !== null && (
                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <Badge
                        variant={storage.direct_uploads ? 'success' : 'warning'}
                    >
                        {storage.direct_uploads
                            ? 'Direct uploads on'
                            : 'Direct uploads off'}
                    </Badge>
                    <span className="text-muted-foreground">
                        {storage.direct_uploads
                            ? `Files go from the browser straight to the bucket when uploaded at ${storage.origin}.`
                            : `The bucket does not take files from pages at ${storage.origin} yet, so every upload passes through this server first.`}
                    </span>
                    {!storage.direct_uploads && (
                        <Form
                            {...ConnectionsController.directUploads.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Allow direct uploads
                                    </Button>
                                    {errors.direct_uploads && (
                                        <p className="mt-2 text-sm text-red-600">
                                            {errors.direct_uploads}
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    )}
                </div>
            )}
            {storage.from_environment && open && (
                <Form
                    {...ConnectionsController.storage.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="Store new files"
                                htmlFor="storage_disk"
                                error={errors.disk}
                                hint="Applies to new files only. Files stored before stay where they are; move them with php artisan royalties:move-files."
                            >
                                <SelectInput
                                    id="storage_disk"
                                    name="disk"
                                    value={disk}
                                    onChange={(event) =>
                                        setDisk(event.target.value)
                                    }
                                >
                                    <option value="s3">
                                        In an S3-compatible bucket
                                    </option>
                                    <option value="local">
                                        On this server
                                    </option>
                                </SelectInput>
                            </Field>
                            {disk === 's3' && (
                                <>
                                    <Field
                                        label="Bucket"
                                        htmlFor="storage_bucket"
                                        error={errors.bucket}
                                        hint="A different bucket than the one in use makes the files in the old one unreachable until they are copied over. Copy first."
                                        required
                                    >
                                        <Input
                                            id="storage_bucket"
                                            name="bucket"
                                            autoComplete="off"
                                            required
                                        />
                                    </Field>
                                    <Field
                                        label="Endpoint"
                                        htmlFor="storage_endpoint"
                                        error={errors.endpoint}
                                        hint="For every provider other than Amazon, e.g. https://fsn1.your-objectstorage.com."
                                    >
                                        <Input
                                            id="storage_endpoint"
                                            name="endpoint"
                                            type="url"
                                            autoComplete="off"
                                        />
                                    </Field>
                                    <Field
                                        label="Region"
                                        htmlFor="storage_region"
                                        error={errors.region}
                                    >
                                        <Input
                                            id="storage_region"
                                            name="region"
                                            autoComplete="off"
                                        />
                                    </Field>
                                    <Field
                                        label="Access key"
                                        htmlFor="storage_key"
                                        error={errors.key}
                                        required
                                    >
                                        <Input
                                            id="storage_key"
                                            name="key"
                                            autoComplete="off"
                                            required
                                        />
                                    </Field>
                                    <Field
                                        label="Secret key"
                                        htmlFor="storage_secret"
                                        error={errors.secret}
                                        required
                                    >
                                        <Input
                                            id="storage_secret"
                                            name="secret"
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                    </Field>
                                    <CheckField
                                        name="path_style"
                                        value="1"
                                        label="Path-style addresses"
                                        hint="Needed by some self-hosted storage such as MinIO."
                                    />
                                </>
                            )}
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Testing the storage…'
                                        : disk === 's3'
                                          ? 'Test and connect'
                                          : 'Use this server'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </ServiceCard>
    );
}

type ServiceInfo = Props['services'][number];

/**
 * The switch of one service with its options. The card saves only its
 * own fields: another service's settings are never part of the form.
 */
function ServiceForm({
    service,
    children,
}: {
    service: ServiceInfo;
    children?: (state: {
        errors: Record<string, string>;
        enabled: boolean;
    }) => ReactNode;
}) {
    const [enabled, setEnabled] = useState(service.enabled);

    return (
        <Form
            {...ConnectionsController.service.form(service.key)}
            options={{ preserveScroll: true }}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <CheckField
                        withZero
                        name="enabled"
                        label={`The label works with ${service.name}`}
                        hint={`Switched off, the label no longer sees its ${service.gates} What is imported stays.`}
                        defaultChecked={service.enabled}
                        onChange={(event) => setEnabled(event.target.checked)}
                    />
                    {children?.({ errors, enabled })}
                    <div>
                        <Button disabled={processing}>Save</Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function ZebralutionOptions({
    zebralution,
    errors,
    enabled,
}: {
    zebralution: Props['zebralution'];
    errors: Record<string, string>;
    enabled: boolean;
}) {
    return (
        <div className={enabled ? 'grid gap-4 sm:grid-cols-2' : 'hidden'}>
            <Field
                label="Zebralution number"
                htmlFor="zebralution_number"
                error={errors.number}
                hint="The number Zebralution gave the label. It goes into every metadata sheet made under Delivery on a release."
            >
                <Input
                    id="zebralution_number"
                    name="number"
                    defaultValue={zebralution.number ?? ''}
                    className="font-mono"
                />
            </Field>
            <Field
                label="Country of origin"
                htmlFor="zebralution_origin_country"
                error={errors.origin_country}
                hint="Where the label's music comes from, as a two-letter code."
            >
                <Input
                    id="zebralution_origin_country"
                    name="origin_country"
                    defaultValue={zebralution.origin_country ?? ''}
                    placeholder="DE"
                    maxLength={2}
                    className="font-mono uppercase"
                />
            </Field>
            <Field
                label="Price group"
                htmlFor="zebralution_price_group"
                error={errors.price_group}
                hint="For tracks and releases alike."
            >
                <SelectInput
                    id="zebralution_price_group"
                    name="price_group"
                    defaultValue={String(zebralution.price_group)}
                >
                    <option value="5">5: back</option>
                    <option value="10">10: mid</option>
                    <option value="20">20: standard</option>
                    <option value="25">25: standard plus</option>
                    <option value="30">30: high</option>
                    <option value="35">35: high plus</option>
                </SelectInput>
            </Field>
            <Field
                label="Rights in compositions and lyrics"
                htmlFor="zebralution_work_rights"
                error={errors.work_rights}
            >
                <SelectInput
                    id="zebralution_work_rights"
                    name="work_rights"
                    defaultValue={zebralution.work_rights}
                >
                    {zebralution.work_rights_options.map((rights) => (
                        <option key={rights} value={rights}>
                            {rights}
                        </option>
                    ))}
                </SelectInput>
            </Field>
        </div>
    );
}

function BandcampOptions({
    bandcamp,
    enabled,
}: {
    bandcamp: Props['bandcamp'];
    enabled: boolean;
}) {
    return (
        <div className={enabled ? 'grid gap-3' : 'hidden'}>
            <CheckField
                withZero
                name="disable_fee"
                label="Do not deduct 1% from Bandcamp net amounts"
                hint="By default 1% is taken off each Bandcamp line to cover payout fees."
                defaultChecked={bandcamp.disable_fee}
            />
            <CheckField
                withZero
                name="exclude_physical"
                label="Skip physical and merch sales"
                hint="Import downloads only, for labels that account physical sales elsewhere."
                defaultChecked={bandcamp.exclude_physical}
            />
        </div>
    );
}

function ServiceCards({
    services,
    zebralution,
    bandcamp,
}: Pick<Props, 'services' | 'zebralution' | 'bandcamp'>) {
    return (
        <>
            {services.map((service) => {
                const isBandcamp = service.key === 'bandcamp';

                return (
                    <ServiceCard
                        key={service.key}
                        title={service.name}
                        kind={service.kind}
                        description={service.purpose}
                        status={
                            !service.enabled
                                ? { label: 'Switched off', variant: 'outline' }
                                : isBandcamp
                                  ? bandcamp.connected
                                      ? CONNECTED
                                      : NOT_CONNECTED
                                  : undefined
                        }
                        facts={
                            isBandcamp && service.enabled && bandcamp.connected
                                ? [
                                      [
                                          'Weekly sync',
                                          bandcamp.enabled ? 'on' : 'off',
                                      ],
                                      [
                                          'Last sync',
                                          bandcamp.last_sync_at
                                              ? dateTime(bandcamp.last_sync_at)
                                              : 'not yet',
                                      ],
                                  ]
                                : []
                        }
                        note={
                            isBandcamp && service.enabled ? (
                                <Link
                                    href={editBandcamp()}
                                    className="underline underline-offset-4"
                                >
                                    {bandcamp.connected
                                        ? 'Accounts, start date, sync and disconnect'
                                        : 'Connect your Bandcamp account'}
                                </Link>
                            ) : undefined
                        }
                    >
                        <ServiceForm service={service}>
                            {({ errors, enabled }) =>
                                service.key === 'zebralution' ? (
                                    <ZebralutionOptions
                                        zebralution={zebralution}
                                        errors={errors}
                                        enabled={enabled}
                                    />
                                ) : isBandcamp ? (
                                    <BandcampOptions
                                        bandcamp={bandcamp}
                                        enabled={enabled}
                                    />
                                ) : null
                            }
                        </ServiceForm>
                    </ServiceCard>
                );
            })}
        </>
    );
}

export default function Connections({
    services,
    zebralution,
    search,
    ai,
    bandcamp,
    storage,
}: Props) {
    return (
        <Page
            title="Connections"
            description="Everything the label works with: the services that deliver and sell its music, and the logins of outside services. Switch a service off to hide what is built for it."
            className="p-0 md:p-0"
        >
            <ServiceCards
                services={services}
                zebralution={zebralution}
                bandcamp={bandcamp}
            />
            <WebSearch search={search} />
            <ReceiptReading ai={ai} />
            {storage && <Storage storage={storage} />}
        </Page>
    );
}

Connections.layout = {
    breadcrumbs: [{ title: 'Connections', href: edit() }],
};
