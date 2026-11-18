import { Link, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import ContractController from '@/actions/App/Http/Controllers/ContractController';
import { DeleteButton } from '@/components/delete-button';
import { Field, SelectInput } from '@/components/field';
import InputError from '@/components/input-error';
import { Page, Panel } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/contracts';
import { show as showRelease } from '@/routes/releases';
import type { Option } from '@/types';

type Split = {
    payee_id: number | '';
    ledger_id: number | '';
    territory_id: number | '';
    percentage: string;
};
type Child = { contract_id: number | ''; weight: string };

type Contract = {
    id: number;
    name: string;
    memo: string | null;
    splits: Split[];
    children: Child[];
    parents: Option[];
    releases: Option[];
    tracks: {
        id: number;
        name: string;
        release: string | null;
        release_id: number;
    }[];
};

type Props = {
    contract: Contract | null;
    payeeOptions: Option[];
    ledgerOptions: Option[];
    territoryOptions: Option[];
    contractOptions: Option[];
};

const numberOrBlank = (value: string): number | '' =>
    value === '' ? '' : Number(value);

export default function ContractForm({
    contract,
    payeeOptions,
    ledgerOptions,
    territoryOptions,
    contractOptions,
}: Props) {
    const form = useForm({
        name: contract?.name ?? '',
        memo: contract?.memo ?? '',
        splits: (contract?.splits ?? []).map((split) => ({
            payee_id: split.payee_id ?? '',
            ledger_id: split.ledger_id ?? '',
            territory_id: split.territory_id ?? '',
            percentage: split.percentage,
        })) as Split[],
        children: (contract?.children ?? []) as Child[],
    });

    const errors = form.errors as Record<string, string>;
    const prorated = form.data.children.length > 0;
    const worldTotal = form.data.splits
        .filter((split) => split.territory_id === '')
        .reduce((sum, split) => sum + (Number(split.percentage) || 0), 0);
    const weightTotal = form.data.children.reduce(
        (sum, child) => sum + (Number(child.weight) || 0),
        0,
    );

    const setSplit = (position: number, changes: Partial<Split>) =>
        form.setData(
            'splits',
            form.data.splits.map((split, current) =>
                current === position ? { ...split, ...changes } : split,
            ),
        );
    const setChild = (position: number, changes: Partial<Child>) =>
        form.setData(
            'children',
            form.data.children.map((child, current) =>
                current === position ? { ...child, ...changes } : child,
            ),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (contract) {
            form.put(ContractController.update.url(contract.id), {
                preserveScroll: true,
            });
        } else {
            form.post(ContractController.store.url());
        }
    };

    return (
        <Page
            title={contract ? contract.name : 'New contract'}
            description="Splits are percentages of net income: income minus the expenses booked on the same release or track."
            actions={
                contract && (
                    <DeleteButton
                        url={ContractController.destroy.url(contract.id)}
                        label="Delete"
                        title={`Delete ${contract.name}?`}
                        description="Releases and tracks using this contract fall back to the next contract up the chain, or to no contract at all. Past statements keep their figures."
                    />
                )
            }
        >
            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <Panel>
                    <div className="grid gap-4">
                        <Field
                            label="Name"
                            htmlFor="name"
                            error={errors.name}
                            required
                            hint='Name it after the deal, e.g. "Artist – Album deal 50/50" or "Remix: Remixer name".'
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field label="Memo" htmlFor="memo" error={errors.memo}>
                            <Textarea
                                id="memo"
                                value={form.data.memo}
                                onChange={(event) =>
                                    form.setData('memo', event.target.value)
                                }
                                placeholder="Term, recoupable costs, anything worth remembering about the deal"
                            />
                        </Field>
                    </div>
                </Panel>

                <Panel
                    title="Splits"
                    description={
                        prorated
                            ? 'Ignored while this contract has child contracts below.'
                            : `Payees get ${worldTotal.toLocaleString('en-GB', { maximumFractionDigits: 4 })}% worldwide, the label keeps ${(100 - worldTotal).toLocaleString('en-GB', { maximumFractionDigits: 4 })}%.`
                    }
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('splits', [
                                    ...form.data.splits,
                                    {
                                        payee_id: '',
                                        ledger_id: '',
                                        territory_id: '',
                                        percentage: '',
                                    },
                                ])
                            }
                        >
                            <Plus />
                            Add split
                        </Button>
                    }
                >
                    {form.data.splits.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No splits: the label keeps everything booked on this
                            contract.
                        </p>
                    ) : (
                        <div className="grid gap-3">
                            <div className="hidden grid-cols-[1fr_7rem_1fr_1fr_2.25rem] gap-2 text-xs font-medium text-muted-foreground md:grid">
                                <span>Payee</span>
                                <span>Split %</span>
                                <span>Ledger</span>
                                <span>Territory</span>
                                <span />
                            </div>
                            {form.data.splits.map((split, position) => (
                                <div
                                    key={position}
                                    className="grid gap-2 md:grid-cols-[1fr_7rem_1fr_1fr_2.25rem]"
                                >
                                    <div>
                                        <SelectInput
                                            aria-label="Payee"
                                            value={split.payee_id}
                                            onChange={(event) =>
                                                setSplit(position, {
                                                    payee_id: numberOrBlank(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                            options={payeeOptions}
                                            placeholder="Choose payee…"
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `splits.${position}.payee_id`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Input
                                            aria-label="Split percent"
                                            type="number"
                                            step="any"
                                            min="0"
                                            max="100"
                                            value={split.percentage}
                                            onChange={(event) =>
                                                setSplit(position, {
                                                    percentage:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder="50"
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `splits.${position}.percentage`
                                                ]
                                            }
                                        />
                                    </div>
                                    <SelectInput
                                        aria-label="Ledger"
                                        value={split.ledger_id}
                                        onChange={(event) =>
                                            setSplit(position, {
                                                ledger_id: numberOrBlank(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                        options={ledgerOptions}
                                        placeholder="Primary ledger"
                                    />
                                    <SelectInput
                                        aria-label="Territory"
                                        value={split.territory_id}
                                        onChange={(event) =>
                                            setSplit(position, {
                                                territory_id: numberOrBlank(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                        options={territoryOptions}
                                        placeholder="World"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remove split"
                                        onClick={() =>
                                            form.setData(
                                                'splits',
                                                form.data.splits.filter(
                                                    (_, current) =>
                                                        current !== position,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ))}
                            {worldTotal > 100 && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    The worldwide splits add up to more than
                                    100%. The label would pay out more than it
                                    earns.
                                </p>
                            )}
                        </div>
                    )}
                </Panel>

                <Panel
                    title="Prorated across other contracts"
                    description="For compilations and bundles: income on this contract is divided between the contracts below by weight, and each pays its own splits."
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('children', [
                                    ...form.data.children,
                                    { contract_id: '', weight: '1' },
                                ])
                            }
                        >
                            <Plus />
                            Add contract
                        </Button>
                    }
                >
                    {form.data.children.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Not prorated. Leave empty for a normal contract.
                        </p>
                    ) : (
                        <div className="grid gap-3">
                            {form.data.children.map((child, position) => (
                                <div
                                    key={position}
                                    className="grid gap-2 md:grid-cols-[1fr_7rem_6rem_2.25rem] md:items-center"
                                >
                                    <div>
                                        <SelectInput
                                            aria-label="Contract"
                                            value={child.contract_id}
                                            onChange={(event) =>
                                                setChild(position, {
                                                    contract_id: numberOrBlank(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                            options={contractOptions}
                                            placeholder="Choose contract…"
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `children.${position}.contract_id`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Input
                                            aria-label="Weight"
                                            type="number"
                                            step="any"
                                            min="0"
                                            value={child.weight}
                                            onChange={(event) =>
                                                setChild(position, {
                                                    weight: event.target.value,
                                                })
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `children.${position}.weight`
                                                ]
                                            }
                                        />
                                    </div>
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {weightTotal > 0
                                            ? `${(((Number(child.weight) || 0) / weightTotal) * 100).toLocaleString('en-GB', { maximumFractionDigits: 2 })}%`
                                            : '–'}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remove contract"
                                        onClick={() =>
                                            form.setData(
                                                'children',
                                                form.data.children.filter(
                                                    (_, current) =>
                                                        current !== position,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ))}
                            <p className="text-xs text-muted-foreground">
                                Tip: use the number of tracks each contract
                                contributes as its weight.
                            </p>
                        </div>
                    )}
                </Panel>

                <div className="flex items-center gap-3">
                    <Button disabled={form.processing}>
                        {contract ? 'Save contract' : 'Create contract'}
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={index()}>Cancel</Link>
                    </Button>
                </div>
            </form>

            {contract && (
                <Panel
                    title="Where this contract applies"
                    className="max-w-4xl"
                >
                    {contract.releases.length +
                        contract.tracks.length +
                        contract.parents.length ===
                    0 ? (
                        <p className="text-sm text-muted-foreground">
                            Not assigned yet. Pick this contract on a release or
                            track, or use the catalog manager to assign many at
                            once.
                        </p>
                    ) : (
                        <ul className="grid gap-1 text-sm">
                            {contract.releases.map((release) => (
                                <li key={`release-${release.id}`}>
                                    <span className="text-muted-foreground">
                                        Release ·{' '}
                                    </span>
                                    <Link
                                        href={showRelease(release.id)}
                                        className="underline decoration-muted-foreground/40 underline-offset-4"
                                    >
                                        {release.name}
                                    </Link>
                                </li>
                            ))}
                            {contract.tracks.map((track) => (
                                <li key={`track-${track.id}`}>
                                    <span className="text-muted-foreground">
                                        Track ·{' '}
                                    </span>
                                    <Link
                                        href={showRelease(track.release_id)}
                                        className="underline decoration-muted-foreground/40 underline-offset-4"
                                    >
                                        {track.name}
                                    </Link>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        on {track.release}
                                    </span>
                                </li>
                            ))}
                            {contract.parents.map((parent) => (
                                <li key={`parent-${parent.id}`}>
                                    <span className="text-muted-foreground">
                                        Part of prorated contract ·{' '}
                                    </span>
                                    <Link
                                        href={ContractController.edit.url(
                                            parent.id,
                                        )}
                                        className="underline decoration-muted-foreground/40 underline-offset-4"
                                    >
                                        {parent.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            )}
        </Page>
    );
}

ContractForm.layout = {
    breadcrumbs: [
        { title: 'Contracts', href: index() },
        { title: 'Contract', href: '#' },
    ],
};
