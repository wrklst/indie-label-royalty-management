import { Form } from '@inertiajs/react';
import LabelController from '@/actions/App/Http/Controllers/LabelController';
import { Field } from '@/components/field';
import { Page, Panel } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function CreateLabel({ isFirst }: { isFirst: boolean }) {
    return (
        <Page
            title={isFirst ? 'Set up your label' : 'New label'}
            description="Each label keeps its own catalog, contracts, transactions and statements. You can switch between labels from the sidebar."
        >
            <Panel className="max-w-xl">
                <Form {...LabelController.store.form()} className="grid gap-4">
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
                                    required
                                    autoFocus
                                    placeholder="My Label"
                                />
                            </Field>
                            <Field
                                label="Accounting currency"
                                htmlFor="currency"
                                error={errors.currency}
                                hint="Statements and balances are kept in this currency. Income in other currencies is converted when you import it."
                                required
                            >
                                <Input
                                    id="currency"
                                    name="currency"
                                    defaultValue="EUR"
                                    maxLength={3}
                                    className="w-24 uppercase"
                                    required
                                />
                            </Field>
                            <div>
                                <Button disabled={processing}>
                                    Create label
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </Panel>
        </Page>
    );
}

CreateLabel.layout = { breadcrumbs: [{ title: 'New label', href: '#' }] };
