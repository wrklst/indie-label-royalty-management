import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
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
        airplay_markets: string[];
    };
    royaltyTools: {
        mechanicals_usd_rate: string | null;
        mechanicals_include_physical: boolean;
        ignore_negative_quantities: boolean;
    };
    territories: { id: number; name: string; countries: string[] }[];
};

function Toggle({
    name,
    label,
    hint,
    defaultChecked,
    onChange,
}: {
    name: string;
    label: string;
    hint?: string;
    defaultChecked: boolean;
    onChange?: (checked: boolean) => void;
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
                onChange={(event) => onChange?.(event.target.checked)}
            />
        </>
    );
}

export default function LabelSettings({
    settings,
    royaltyTools,
    territories,
}: Props) {
    const [tools, setTools] = useState({
        mechanicals: settings.accounts_mechanicals,
        ppd: settings.accounts_ppd,
    });

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
                            >
                                <Input
                                    id="airplay_markets"
                                    name="airplay_markets"
                                    defaultValue={settings.airplay_markets.join(
                                        ' ',
                                    )}
                                    placeholder="DE GB US"
                                    className="font-mono uppercase"
                                    required
                                />
                            </Field>

                            <div className="grid gap-3">
                                <p className="text-sm font-medium">
                                    Accounting features
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Each feature adds a tool under Royalties.
                                    Nothing is calculated on its own: you run
                                    the tool for a range of paid dates and it
                                    creates ordinary transactions.
                                </p>
                                <Toggle
                                    name="accounts_mechanicals"
                                    label="Mechanicals"
                                    hint="Calculate mechanical royalties owed to songwriters for digital downloads and physical manufacturing or sales."
                                    defaultChecked={
                                        settings.accounts_mechanicals
                                    }
                                    onChange={(checked) =>
                                        setTools({
                                            ...tools,
                                            mechanicals: checked,
                                        })
                                    }
                                />
                                {tools.mechanicals && (
                                    <div className="ml-6 grid gap-3 border-l pl-4">
                                        <Toggle
                                            name="mechanicals_include_physical"
                                            label="Include physical sales"
                                            hint="Count sold physical copies as units, next to downloads. Leave off when you enter manufacturing runs instead."
                                            defaultChecked={
                                                royaltyTools.mechanicals_include_physical
                                            }
                                        />
                                        <Field
                                            label={`Statutory rate conversion: 1 USD = ? ${settings.currency}`}
                                            htmlFor="mechanicals_usd_rate"
                                            error={errors.mechanicals_usd_rate}
                                            hint="The statutory mechanical rates are in US dollars. Needed for tracks without their own rate when the label currency is not USD."
                                        >
                                            <Input
                                                id="mechanicals_usd_rate"
                                                name="mechanicals_usd_rate"
                                                type="number"
                                                step="any"
                                                min="0"
                                                className="w-40"
                                                defaultValue={
                                                    royaltyTools.mechanicals_usd_rate ??
                                                    ''
                                                }
                                            />
                                        </Field>
                                    </div>
                                )}
                                <Toggle
                                    name="accounts_ppd"
                                    label="PPD"
                                    hint="Calculate royalties based on PPD (Published Price to Dealer) rates and transaction quantities."
                                    defaultChecked={settings.accounts_ppd}
                                    onChange={(checked) =>
                                        setTools({ ...tools, ppd: checked })
                                    }
                                />
                                {(tools.mechanicals || tools.ppd) && (
                                    <div className="ml-6 grid gap-3 border-l pl-4">
                                        <Toggle
                                            name="ignore_negative_quantities"
                                            label="Ignore negative quantities"
                                            hint="By default returns reduce the units mechanicals and PPD are calculated on."
                                            defaultChecked={
                                                royaltyTools.ignore_negative_quantities
                                            }
                                        />
                                    </div>
                                )}
                                <Toggle
                                    name="accounts_reserves"
                                    label="Reserves"
                                    hint="Hold a percentage of income for custom periods of time to account for returns etc."
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
                                    errors.countries ??
                                    Object.entries(errors).find(([key]) =>
                                        key.startsWith('countries.'),
                                    )?.[1]
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
