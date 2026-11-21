import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LabelSettingsController from '@/actions/App/Http/Controllers/Settings/LabelSettingsController';
import { DeleteButton } from '@/components/delete-button';
import { CheckField, Field } from '@/components/field';
import { Page, Panel } from '@/components/page';
import { LabelImageUpload } from '@/components/label-image-upload';
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
    };
    royaltyTools: {
        mechanicals_usd_rate: string | null;
        mechanicals_include_physical: boolean;
        ignore_negative_quantities: boolean;
    };
    territories: {
        id: number;
        name: string;
        countries: string[];
        delete_block: string | null;
    }[];
};

export default function LabelSettings({
    settings,
    royaltyTools,
    territories,
}: Props) {
    const { label } = usePage().props;
    const [tools, setTools] = useState({
        mechanicals: settings.accounts_mechanicals,
        ppd: settings.accounts_ppd,
    });

    return (
        <Page
            title="Label"
            description="The name, currency and images of this label, the accounting features it uses and its territories. The services it works with are under Connections."
            className="p-0 md:p-0"
        >
            <Panel
                title="Logo and signet"
                description="The images that stand for the label on statements and in the sidebar."
            >
                <div className="grid gap-6 sm:grid-cols-2">
                    <LabelImageUpload
                        kind="logo"
                        title="Logo"
                        hint="Shown at the top of statements. PNG, JPEG or WebP, up to 4 MB; a transparent PNG works on light and dark."
                        current={label?.logo_url ?? null}
                    />
                    <LabelImageUpload
                        kind="signet"
                        title="Signet"
                        hint="The small square mark, shown in the sidebar next to the label's name."
                        current={label?.signet_url ?? null}
                    />
                </div>
            </Panel>

            <Form
                {...LabelSettingsController.update.form()}
                options={{ preserveScroll: true }}
                className="grid gap-6"
            >
                {({ errors, processing }) => (
                    <>
                        <Panel
                            title="Details"
                            description="The name, accounting currency and contact address of the label."
                        >
                            <div className="grid gap-6">
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
                                        defaultValue={
                                            settings.contact_email ?? ''
                                        }
                                    />
                                </Field>
                            </div>
                        </Panel>

                        <Panel
                            title="Accounting features"
                            description="Each feature adds a tool under Accounting. Nothing is calculated on its own: you run the tool for a range of paid dates and it creates ordinary transactions."
                        >
                            <div className="grid gap-3">
                                <CheckField
                                    withZero
                                    name="accounts_mechanicals"
                                    label="Mechanicals"
                                    hint="Calculate mechanical royalties owed to songwriters for digital downloads and physical manufacturing or sales."
                                    defaultChecked={
                                        settings.accounts_mechanicals
                                    }
                                    onChange={(event) =>
                                        setTools({
                                            ...tools,
                                            mechanicals: event.target.checked,
                                        })
                                    }
                                />
                                {tools.mechanicals && (
                                    <div className="ml-6 grid gap-3 border-l pl-4">
                                        <CheckField
                                            withZero
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
                                <CheckField
                                    withZero
                                    name="accounts_ppd"
                                    label="PPD"
                                    hint="Calculate royalties based on PPD (Published Price to Dealer) rates and transaction units."
                                    defaultChecked={settings.accounts_ppd}
                                    onChange={(event) =>
                                        setTools({
                                            ...tools,
                                            ppd: event.target.checked,
                                        })
                                    }
                                />
                                {(tools.mechanicals || tools.ppd) && (
                                    <div className="ml-6 grid gap-3 border-l pl-4">
                                        <CheckField
                                            withZero
                                            name="ignore_negative_quantities"
                                            label="Ignore negative units"
                                            hint="By default returns reduce the units mechanicals and PPD are calculated on."
                                            defaultChecked={
                                                royaltyTools.ignore_negative_quantities
                                            }
                                        />
                                    </div>
                                )}
                                <CheckField
                                    withZero
                                    name="accounts_reserves"
                                    label="Reserves"
                                    hint="Hold a percentage of income for custom periods of time to account for returns etc."
                                    defaultChecked={settings.accounts_reserves}
                                />
                            </div>
                        </Panel>

                        <div>
                            <Button disabled={processing}>Save</Button>
                        </div>
                    </>
                )}
            </Form>

            <Panel
                title="Territories"
                description="Groups of countries for splits that only apply in part of the world. A split without a territory applies everywhere."
            >
                <div className="space-y-6">
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
                                        blocked={territory.delete_block}
                                        description="The territory is removed. No contract split uses it."
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
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Add territory
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </Panel>
        </Page>
    );
}

LabelSettings.layout = {
    breadcrumbs: [{ title: 'Label', href: edit() }],
};
