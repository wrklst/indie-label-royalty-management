import { Pencil, Plus } from 'lucide-react';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
import { DeleteButton } from '@/components/delete-button';
import { Field } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { EmptyState, Page, Panel } from '@/components/page';
import { SortableHead } from '@/components/sortable-head';
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
import { useTableSort } from '@/hooks/use-table-sort';
import { index } from '@/routes/ledgers';

type Ledger = {
    id: number;
    name: string;
    splits_count: number;
    payees: string[];
    contracts: string[];
};

function NameField({
    ledger,
    errors,
}: {
    ledger?: Ledger;
    errors: Record<string, string>;
}) {
    return (
        <Field label="Name" htmlFor="name" error={errors.name} required>
            <Input
                id="name"
                name="name"
                defaultValue={ledger?.name}
                placeholder="Album advance 2026"
                required
                autoFocus
            />
        </Field>
    );
}

const SORT_COLUMNS = {
    name: (row: Ledger) => row.name,
    payees: (row: Ledger) => row.payees.join(', '),
    contracts: (row: Ledger) => row.contracts.join(', '),
};

export default function Ledgers({ ledgers }: { ledgers: Ledger[] }) {
    const { rows, sorter } = useTableSort(ledgers, SORT_COLUMNS);

    return (
        <Page
            title="Ledgers"
            description="A ledger is a separate balance for a payee. Every split books to the primary ledger unless you pick another one. Use an extra ledger to recoup an advance or album costs on its own, without touching what the payee earns elsewhere."
            actions={
                <FormDialog
                    title="New ledger"
                    form={LedgerController.store.form()}
                    submitLabel="Create ledger"
                    trigger={
                        <Button>
                            <Plus />
                            New ledger
                        </Button>
                    }
                >
                    {(errors) => <NameField errors={errors} />}
                </FormDialog>
            }
        >
            {ledgers.length === 0 ? (
                <EmptyState
                    title="Only the primary ledger so far"
                    description="That is all most labels need. Add a ledger when a payee has a deal whose balance must not mix with their other earnings."
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead column="name" sorter={sorter}>
                                    Name
                                </SortableHead>
                                <SortableHead column="payees" sorter={sorter}>
                                    Payees
                                </SortableHead>
                                <SortableHead
                                    column="contracts"
                                    sorter={sorter}
                                >
                                    Used in contracts
                                </SortableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((ledger) => (
                                <TableRow key={ledger.id}>
                                    <TableCell className="font-medium">
                                        {ledger.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {ledger.payees.join(', ') || '–'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {ledger.contracts.join(', ') ||
                                            'Not used yet'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <FormDialog
                                                title={`Rename ${ledger.name}`}
                                                form={LedgerController.update.form(
                                                    ledger.id,
                                                )}
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Rename ${ledger.name}`}
                                                    >
                                                        <Pencil className="text-muted-foreground" />
                                                    </Button>
                                                }
                                            >
                                                {(errors) => (
                                                    <NameField
                                                        ledger={ledger}
                                                        errors={errors}
                                                    />
                                                )}
                                            </FormDialog>
                                            <DeleteButton
                                                url={LedgerController.destroy.url(
                                                    ledger.id,
                                                )}
                                                title={`Delete ${ledger.name}?`}
                                                description={`${ledger.splits_count} split(s) and any payouts booked to this ledger move to the primary ledger. Balances will change.`}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>
            )}
        </Page>
    );
}

Ledgers.layout = { breadcrumbs: [{ title: 'Ledgers', href: index() }] };
