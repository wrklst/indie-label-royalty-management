import { Form, Head } from '@inertiajs/react';
import LabelSettingsController from '@/actions/App/Http/Controllers/Settings/LabelSettingsController';
import { DeleteButton } from '@/components/delete-button';
import { CheckField, Field } from '@/components/field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit } from '@/routes/label-settings';

type Props = {
    settings: {
        name: string;
        currency: string;
        contact_email: string | null;
        accounts_mechanicals: boolean;
        accounts_ppd: boolean;
        accounts_reserves: boolean;
        bandcamp_disable_fee: boolean;
        bandcamp_exclude_physical: boolean;
    };
    territories: { id: number; name: string; countries: string[] }[];
};

function Toggle({
    name,
    label,
    hint,
    defaultChecked,
}: {
    name: string;
    label: string;
    hint?: string;
    defaultChecked: boolean;
}) {
    return (
        <>
            <input type="hidden" name={name} value="0" />
            <CheckField
                name={name}
                value="1"
                label={label}
                hint={hint}
                defaultChecked={defaultChecked}
            />
        </>
    );
}

export default function LabelSettings({ settings, territories }: Props) {
    return (
        <>
            <Head title="Label settings" />
            <h1 className="sr-only">Label settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Label"
                    description="Name, accounting currency and how imports behave for this label"
                />

                <Form
                    {...LabelSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="Label name"
                                htmlFor="name"
                                error={errors.name}
                                required
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={settings.name}
                                    required
                                />
                            </Field>
                            <Field
                                label="Accounting currency"
                                htmlFor="currency"
                                error={errors.currency}
                                required
                                hint="Changing it does not convert existing transactions. Only change it before you import."
                            >
                                <Input
                                    id="currency"
                                    name="currency"
                                    defaultValue={settings.currency}
                                    maxLength={3}
                                    className="w-24 uppercase"
                                    required
                                />
                            </Field>
                            <Field
                                label="Contact email"
                                htmlFor="contact_email"
                                error={errors.contact_email}
                            >
                                <Input
                                    id="contact_email"
                                    name="contact_email"
                                    type="email"
                                    defaultValue={settings.contact_email ?? ''}
                                />
                            </Field>

                            <div className="grid gap-3">
                                <p className="text-sm font-medium">
                                    Bandcamp imports
                                </p>
                                <Toggle
                                    name="bandcamp_disable_fee"
                                    label="Do not deduct 1% from Bandcamp net amounts"
                                    hint="By default 1% is taken off each Bandcamp line to cover payout fees."
                                    defaultChecked={
                                        settings.bandcamp_disable_fee
                                    }
                                />
                                <Toggle
                                    name="bandcamp_exclude_physical"
                                    label="Skip physical and merch sales"
                                    hint="Import downloads only, for labels that account physical sales elsewhere."
                                    defaultChecked={
                                        settings.bandcamp_exclude_physical
                                    }
                                />
                            </div>

                            <div className="grid gap-3">
                                <p className="text-sm font-medium">
                                    Accounting features
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Recorded for the label. Mechanicals, PPD and
                                    reserves rates can be stored on releases but
                                    are not calculated on statements yet.
                                </p>
                                <Toggle
                                    name="accounts_mechanicals"
                                    label="We account mechanicals"
                                    defaultChecked={
                                        settings.accounts_mechanicals
                                    }
                                />
                                <Toggle
                                    name="accounts_ppd"
                                    label="We account by PPD"
                                    defaultChecked={settings.accounts_ppd}
                                />
                                <Toggle
                                    name="accounts_reserves"
                                    label="We hold reserves"
                                    defaultChecked={settings.accounts_reserves}
                                />
                            </div>

                            <div>
                                <Button disabled={processing}>Save</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="mt-10 space-y-6">
                <Heading
                    variant="small"
                    title="Territories"
                    description="Groups of countries for splits that only apply in part of the world. A split without a territory applies everywhere."
                />

                {territories.length > 0 && (
                    <ul className="divide-y rounded-lg border text-sm">
                        {territories.map((territory) => (
                            <li
                                key={territory.id}
                                className="flex items-center justify-between gap-3 px-3 py-2"
                            >
                                <span className="min-w-0">
                                    <span className="font-medium">
                                        {territory.name}
                                    </span>
                                    <span className="block truncate font-mono text-xs text-muted-foreground">
                                        {territory.countries.join(' ')}
                                    </span>
                                </span>
                                <DeleteButton
                                    url={LabelSettingsController.destroyTerritory.url(
                                        territory.id,
                                    )}
                                    title={`Delete ${territory.name}?`}
                                    description="Splits limited to this territory will apply worldwide instead. Check the contracts that use it first."
                                />
                            </li>
                        ))}
                    </ul>
                )}

                <Form
                    {...LabelSettingsController.storeTerritory.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="Territory name"
                                htmlFor="territory_name"
                                error={errors.name}
                                required
                            >
                                <Input
                                    id="territory_name"
                                    name="name"
                                    placeholder="GSA"
                                    required
                                />
                            </Field>
                            <Field
                                label="Countries"
                                htmlFor="countries"
                                error={
                                    errors.countries ?? errors['countries.0']
                                }
                                required
                                hint="Two-letter country codes separated by spaces or commas."
                            >
                                <Input
                                    id="countries"
                                    name="countries"
                                    placeholder="DE AT CH"
                                    className="font-mono uppercase"
                                    required
                                />
                            </Field>
                            <div>
                                <Button variant="outline" disabled={processing}>
                                    Add territory
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

LabelSettings.layout = {
    breadcrumbs: [{ title: 'Label settings', href: edit() }],
};
