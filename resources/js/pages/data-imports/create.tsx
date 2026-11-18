import { Link, useForm } from '@inertiajs/react';
import DataImportController from '@/actions/App/Http/Controllers/DataImportController';
import { CheckField, Field } from '@/components/field';
import { Page, Panel } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index, template } from '@/routes/data-imports';

type Source = {
    key: string;
    name: string;
    requires_paid_date: boolean;
    versions: { key: string; version: string | null; description: string }[];
};

type Importer = {
    key: string;
    name: string;
    description: string;
    requires_paid_date: boolean;
};

const AUTO = 'auto';

export default function CreateDataImport({
    sources,
    currency,
}: {
    sources: Source[];
    currency: string;
}) {
    const needPaidDate = sources
        .filter((source) => source.requires_paid_date)
        .map((source) => source.name);
    const importers: Importer[] = [
        {
            key: AUTO,
            name: 'Detect automatically',
            description:
                'The source and layout are recognised from the columns of the file.',
            requires_paid_date: false,
        },
        ...sources.map((source) => ({
            key: source.key,
            name: source.name,
            description:
                source.versions.length === 1
                    ? source.versions[0].description
                    : `Layouts recognised: ${source.versions.map((version) => version.version).join('; ')}.`,
            requires_paid_date: source.requires_paid_date,
        })),
    ];
    const form = useForm({
        importer: AUTO,
        file: null as File | null,
        name: '',
        default_timestamp: '',
        replace_timestamp: false,
        default_paid_timestamp: '',
        replace_paid_timestamp: false,
        conversion_rate: '',
        skip_duplicates: true,
        tags: '',
    });

    const errors = form.errors;
    const importer = importers.find(
        (candidate) => candidate.key === form.data.importer,
    );
    const usesTemplate =
        form.data.importer === 'template' ||
        form.data.importer === 'audio-rights';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(DataImportController.store.url(), { forceFormData: true });
    };

    return (
        <Page
            title="Import a statement"
            description="The file is checked and imported in one go. If anything stops the import, nothing is saved."
        >
            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <Panel title="1. Where is the file from?">
                    <div
                        className="grid gap-2 sm:grid-cols-2"
                        role="radiogroup"
                        aria-label="Importer"
                    >
                        {importers.map((candidate) => (
                            <label
                                key={candidate.key}
                                className={cn(
                                    'flex cursor-pointer gap-3 rounded-lg border p-3 text-sm transition-colors',
                                    candidate.key === form.data.importer
                                        ? 'border-foreground bg-muted/50'
                                        : 'hover:bg-muted/30',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="importer"
                                    className="mt-1 accent-primary"
                                    checked={
                                        candidate.key === form.data.importer
                                    }
                                    onChange={() =>
                                        form.setData('importer', candidate.key)
                                    }
                                />
                                <span>
                                    <span className="font-medium">
                                        {candidate.name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {candidate.description}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {errors.importer && (
                        <p className="mt-2 text-sm text-red-600">
                            {errors.importer}
                        </p>
                    )}
                    {usesTemplate && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            <a
                                href={template.url()}
                                className="underline underline-offset-4"
                            >
                                Download the CSV template
                            </a>{' '}
                            with all supported columns and an example row. The
                            same columns work in an Excel file.
                        </p>
                    )}
                </Panel>

                <Panel title="2. File">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            label="Statement file"
                            htmlFor="file"
                            error={errors.file}
                            required
                            hint="CSV or Excel (.xlsx), up to 50 MB. Encoding, separator and title lines above the header are handled."
                        >
                            <Input
                                id="file"
                                type="file"
                                accept=".csv,.txt,.tsv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                required
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Name"
                            htmlFor="name"
                            error={errors.name}
                            hint="Defaults to the file name."
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Zebralution 2026 Q3"
                            />
                        </Field>
                    </div>
                </Panel>

                <Panel
                    title="3. Dates"
                    description="A transaction counts in the period its paid date falls in."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid content-start gap-2">
                            <Field
                                label="Paid date"
                                htmlFor="default_paid_timestamp"
                                error={errors.default_paid_timestamp}
                                required={importer?.requires_paid_date}
                                hint={
                                    importer?.requires_paid_date
                                        ? 'This source does not say when you were paid. Enter the day the money arrived.'
                                        : form.data.importer === AUTO &&
                                            needPaidDate.length > 0
                                          ? `Required for ${needPaidDate.join(' and ')} files, which do not say when you were paid. Otherwise used for lines without a paid date.`
                                          : 'Used for lines without a paid date.'
                                }
                            >
                                <Input
                                    id="default_paid_timestamp"
                                    type="date"
                                    value={form.data.default_paid_timestamp}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_paid_timestamp',
                                            event.target.value,
                                        )
                                    }
                                    required={importer?.requires_paid_date}
                                />
                            </Field>
                            {!importer?.requires_paid_date && (
                                <CheckField
                                    label="Use this paid date for every line"
                                    checked={form.data.replace_paid_timestamp}
                                    onChange={(event) =>
                                        form.setData(
                                            'replace_paid_timestamp',
                                            event.target.checked,
                                        )
                                    }
                                />
                            )}
                        </div>
                        <div className="grid content-start gap-2">
                            <Field
                                label="Sale date"
                                htmlFor="default_timestamp"
                                error={errors.default_timestamp}
                                hint="Used for lines without a sale date."
                            >
                                <Input
                                    id="default_timestamp"
                                    type="date"
                                    value={form.data.default_timestamp}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_timestamp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <CheckField
                                label="Use this sale date for every line"
                                checked={form.data.replace_timestamp}
                                onChange={(event) =>
                                    form.setData(
                                        'replace_timestamp',
                                        event.target.checked,
                                    )
                                }
                            />
                        </div>
                    </div>
                </Panel>

                <Panel title="4. Options">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            label={`Conversion rate to ${currency}`}
                            htmlFor="conversion_rate"
                            error={errors.conversion_rate}
                            hint={`Only for lines in another currency: 1 unit = ? ${currency}. Without a rate those lines are imported but not counted until you set one.`}
                        >
                            <Input
                                id="conversion_rate"
                                type="number"
                                step="any"
                                min="0"
                                value={form.data.conversion_rate}
                                onChange={(event) =>
                                    form.setData(
                                        'conversion_rate',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Tags"
                            htmlFor="tags"
                            error={errors.tags}
                            hint="Separate with commas."
                        >
                            <Input
                                id="tags"
                                value={form.data.tags}
                                onChange={(event) =>
                                    form.setData('tags', event.target.value)
                                }
                            />
                        </Field>
                        <CheckField
                            label="Skip lines that were already imported"
                            hint="Protects against uploading the same statement twice. Switch off only to import a file again on purpose."
                            checked={form.data.skip_duplicates}
                            onChange={(event) =>
                                form.setData(
                                    'skip_duplicates',
                                    event.target.checked,
                                )
                            }
                            className="md:col-span-2"
                        />
                    </div>
                </Panel>

                <div className="flex items-center gap-3">
                    <Button disabled={form.processing}>
                        {form.processing ? 'Importing…' : 'Import'}
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={index()}>Cancel</Link>
                    </Button>
                    {form.progress && (
                        <span className="text-sm text-muted-foreground">
                            Uploading {form.progress.percentage}%
                        </span>
                    )}
                </div>
            </form>
        </Page>
    );
}

CreateDataImport.layout = {
    breadcrumbs: [
        { title: 'Data imports', href: index() },
        { title: 'New import', href: '#' },
    ],
};
