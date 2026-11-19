import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ConnectionsController from '@/actions/App/Http/Controllers/Settings/ConnectionsController';
import { CheckField, Field, SelectInput } from '@/components/field';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

type Props = {
    search: {
        login: string | null;
        password_set: boolean;
        from_environment: boolean;
    };
    ai: {
        provider: string | null;
        key_set: boolean;
        endpoint: string | null;
        model: string | null;
        services: Service[];
    };
    bandcamp: {
        connected: boolean;
        enabled: boolean;
        last_sync_at: string | null;
    };
    storage: {
        disk: string;
        from_environment: boolean;
        bucket: string | null;
        region: string | null;
        endpoint: string | null;
        path_style: boolean;
        key_set: boolean;
    } | null;
};

const stored = (isSet: boolean) =>
    isSet ? 'Stored. Leave empty to keep it.' : undefined;

function WebSearch({ search }: { search: Props['search'] }) {
    return (
        <section className="space-y-6" aria-labelledby="web-search">
            <Heading
                variant="small"
                title="Web search"
                description="The airplay research looks tracks up on Google through DataForSEO. Its API login and password are in your DataForSEO dashboard under API Access; every search is billed to that account."
            />
            {search.from_environment && (
                <p className="text-sm text-muted-foreground">
                    A login from the server&apos;s environment file is in use.
                    Enter one here to use your own instead.
                </p>
            )}
            <Form
                {...ConnectionsController.search.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess={['password']}
                className="grid gap-6"
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="DataForSEO API login"
                            htmlFor="search_login"
                            error={errors.login}
                            hint="Leave empty to remove the login."
                        >
                            <Input
                                id="search_login"
                                name="login"
                                defaultValue={search.login ?? ''}
                                autoComplete="off"
                            />
                        </Field>
                        <Field
                            label="DataForSEO API password"
                            htmlFor="search_password"
                            error={errors.password}
                            hint={stored(search.password_set)}
                        >
                            <Input
                                id="search_password"
                                name="password"
                                type="password"
                                autoComplete="new-password"
                            />
                        </Field>
                        <div>
                            <Button disabled={processing}>
                                Save web search
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function ReceiptReading({ ai }: { ai: Props['ai'] }) {
    const [provider, setProvider] = useState(ai.provider ?? '');
    const service = ai.services.find((entry) => entry.value === provider);

    return (
        <section className="space-y-6" aria-labelledby="receipt-reading">
            <Heading
                variant="small"
                title="Receipt reading"
                description="An AI service reads vendor, date, amount and category off receipts you upload under Label costs. The receipts are sent to the service you choose and billed to your account there. Without a service, receipts are stored and booked by hand."
            />
            <Form
                {...ConnectionsController.ai.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess={['key']}
                className="grid gap-6"
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="AI service"
                            htmlFor="ai_provider"
                            error={errors.provider}
                        >
                            <SelectInput
                                id="ai_provider"
                                name="provider"
                                value={provider}
                                onChange={(event) =>
                                    setProvider(event.target.value)
                                }
                            >
                                <option value="">
                                    None: do not read receipts
                                </option>
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
                                        provider === ai.provider
                                            ? stored(ai.key_set)
                                            : provider === 'infomaniak'
                                              ? 'An API token from the Infomaniak Manager with the scope for AI Tools.'
                                              : undefined
                                    }
                                >
                                    <Input
                                        id="ai_key"
                                        name="key"
                                        type="password"
                                        autoComplete="new-password"
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
                                            defaultValue={
                                                provider === ai.provider
                                                    ? (ai.endpoint ?? '')
                                                    : ''
                                            }
                                            autoComplete="off"
                                            required
                                        />
                                    </Field>
                                )}
                                <Field
                                    label="Model"
                                    htmlFor="ai_model"
                                    error={errors.model}
                                    hint={
                                        service.model
                                            ? `Leave empty to use ${service.model}.`
                                            : 'The exact name of a model that understands images, as the service writes it. Saving asks the model one short question to prove it answers.'
                                    }
                                    required={!service.model}
                                >
                                    <Input
                                        id="ai_model"
                                        name="model"
                                        defaultValue={
                                            provider === ai.provider
                                                ? (ai.model ?? '')
                                                : ''
                                        }
                                        autoComplete="off"
                                        required={!service.model}
                                    />
                                </Field>
                            </>
                        )}
                        <div>
                            <Button disabled={processing}>
                                Save receipt reading
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function Storage({ storage }: { storage: NonNullable<Props['storage']> }) {
    const [disk, setDisk] = useState(
        storage.from_environment ? 'environment' : storage.disk,
    );

    return (
        <section className="space-y-6" aria-labelledby="file-storage">
            <Heading
                variant="small"
                title="File storage"
                description="Where release files, receipts and statement files of every label on this installation are stored. Only you, as the installation's admin, see this section."
            />
            <p className="text-sm text-muted-foreground">
                In use now:{' '}
                <Badge variant="outline">
                    {storage.disk === 's3'
                        ? `bucket ${storage.bucket ?? ''}`
                        : 'this server'}
                </Badge>{' '}
                {storage.from_environment && 'as set in the environment file.'}
            </p>
            <Form
                {...ConnectionsController.storage.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess={['key', 'secret']}
                className="grid gap-6"
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="Store new files"
                            htmlFor="storage_disk"
                            error={errors.disk}
                            hint="Changing this affects new files only. Files stored before stay where they are; move them with php artisan royalties:move-files."
                        >
                            <SelectInput
                                id="storage_disk"
                                name="disk"
                                value={disk}
                                onChange={(event) =>
                                    setDisk(event.target.value)
                                }
                            >
                                <option value="environment">
                                    As the environment file says
                                </option>
                                <option value="local">On this server</option>
                                <option value="s3">
                                    In an S3-compatible bucket
                                </option>
                            </SelectInput>
                        </Field>
                        {disk === 's3' && (
                            <>
                                <Field
                                    label="Bucket"
                                    htmlFor="storage_bucket"
                                    error={errors.bucket}
                                    hint="Saving writes, reads and deletes a small test file in it. Pointing at a different bucket than the one in use makes the files in the old one unreachable until they are copied over."
                                    required
                                >
                                    <Input
                                        id="storage_bucket"
                                        name="bucket"
                                        defaultValue={storage.bucket ?? ''}
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
                                        defaultValue={storage.endpoint ?? ''}
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
                                        defaultValue={storage.region ?? ''}
                                        autoComplete="off"
                                    />
                                </Field>
                                <Field
                                    label="Access key"
                                    htmlFor="storage_key"
                                    error={errors.key}
                                    hint={stored(storage.key_set)}
                                >
                                    <Input
                                        id="storage_key"
                                        name="key"
                                        autoComplete="off"
                                    />
                                </Field>
                                <Field
                                    label="Secret key"
                                    htmlFor="storage_secret"
                                    error={errors.secret}
                                    hint={stored(storage.key_set)}
                                >
                                    <Input
                                        id="storage_secret"
                                        name="secret"
                                        type="password"
                                        autoComplete="new-password"
                                    />
                                </Field>
                                <CheckField
                                    name="path_style"
                                    value="1"
                                    defaultChecked={storage.path_style}
                                    label="Path-style addresses"
                                    hint="Needed by some self-hosted storage such as MinIO."
                                />
                            </>
                        )}
                        <div>
                            <Button disabled={processing}>
                                Save file storage
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

export default function Connections({ search, ai, bandcamp, storage }: Props) {
    return (
        <>
            <Head title="Connections" />
            <h1 className="sr-only">Connections</h1>

            <div className="space-y-12">
                <WebSearch search={search} />
                <ReceiptReading ai={ai} />

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Bandcamp"
                        description="Fetches your Bandcamp sales every week instead of you uploading sales reports."
                    />
                    <p className="flex flex-wrap items-center gap-3 text-sm">
                        <Badge variant="outline">
                            {bandcamp.connected
                                ? bandcamp.enabled
                                    ? 'Connected, weekly sync on'
                                    : 'Connected, weekly sync off'
                                : 'Not connected'}
                        </Badge>
                        <Link
                            href={editBandcamp()}
                            className="underline underline-offset-4"
                        >
                            {bandcamp.connected
                                ? 'Open the Bandcamp connection'
                                : 'Connect your Bandcamp account'}
                        </Link>
                    </p>
                </section>

                {storage && <Storage storage={storage} />}
            </div>
        </>
    );
}

Connections.layout = {
    breadcrumbs: [{ title: 'Connections', href: edit() }],
};
