import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import DataImportController from '@/actions/App/Http/Controllers/DataImportController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { CheckField } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { Panel } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { date } from '@/lib/format';

export type ToolRun = {
    id: number;
    name: string;
    from: string;
    to: string;
    total: string;
    paid_on: string | null;
    transactions_count: number;
    created_at: string | null;
};

/** Drop trailing zeros from a decimal string: "0.131000" becomes "0.131". */
export const plain = (value: string) =>
    value.includes('.') ? value.replace(/\.?0+$/, '') : value;

// The local date: toISOString() is UTC and gives yesterday shortly after midnight.
export const today = () => new Date().toLocaleDateString('sv-SE');

export function Notice({ children }: { children: ReactNode }) {
    return (
        <div
            role="status"
            className="flex gap-2.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
            <div className="min-w-0 space-y-1">{children}</div>
        </div>
    );
}

export function OverlapNotice({ overlaps }: { overlaps: ToolRun[] }) {
    if (overlaps.length === 0) {
        return null;
    }

    return (
        <Notice>
            <p className="font-medium">
                This range overlaps an earlier run. Booking it again counts the
                same sales twice.
            </p>
            <ul className="list-disc pl-4">
                {overlaps.map((run) => (
                    <li key={run.id}>
                        <Link
                            href={DataImportController.show.url(run.id)}
                            className="underline"
                        >
                            {date(run.from)} – {date(run.to)}
                        </Link>
                    </li>
                ))}
            </ul>
        </Notice>
    );
}

/**
 * The dialog that books a report. The range and options it was calculated
 * with travel along as hidden fields, so the server books exactly what the
 * page shows.
 */
export function CreateTransactions({
    form,
    fields,
    overlaps,
    description,
    children,
}: {
    form: { action: string; method: 'post' };
    fields: Record<string, string | number | null>;
    overlaps: ToolRun[];
    description: ReactNode;
    children: (errors: Record<string, string>) => ReactNode;
}) {
    return (
        <FormDialog
            title="Create transactions"
            description={description}
            form={form}
            submitLabel="Create"
            trigger={
                <Button data-test="create-transactions">
                    Create transactions
                </Button>
            }
        >
            {(errors) => (
                <>
                    {Object.entries(fields).map(
                        ([name, value]) =>
                            value !== null &&
                            value !== '' && (
                                <input
                                    key={name}
                                    type="hidden"
                                    name={name}
                                    value={value}
                                />
                            ),
                    )}
                    {children(errors)}
                    {overlaps.length > 0 && (
                        <div className="grid gap-1.5">
                            <CheckField
                                id="confirm_overlap"
                                name="confirm_overlap"
                                value="1"
                                label="Book it although an earlier run overlaps this range"
                                hint="The same sales are then counted twice. Delete the earlier run instead if this one replaces it."
                            />
                            {errors.confirm_overlap && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {errors.confirm_overlap}
                                </p>
                            )}
                        </div>
                    )}
                    {errors.from && (
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {errors.from}
                        </p>
                    )}
                </>
            )}
        </FormDialog>
    );
}

export function RunsPanel({
    runs,
    currency,
    totalLabel,
}: {
    runs: ToolRun[];
    currency: string;
    totalLabel: string;
}) {
    return (
        <Panel
            title="Earlier runs"
            description="Each run is a data import holding the transactions it created. Deleting the import removes them again."
            flush
        >
            {runs.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">
                    Nothing has been booked with this tool yet.
                </p>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Paid dates covered</TableHead>
                            <TableHead>Booked on</TableHead>
                            <TableHead className="text-right">
                                Transactions
                            </TableHead>
                            <TableHead className="text-right">
                                {totalLabel}
                            </TableHead>
                            <TableHead className="w-12" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {runs.map((run) => (
                            <TableRow key={run.id}>
                                <TableCell className="whitespace-nowrap">
                                    <Link
                                        href={DataImportController.show.url(
                                            run.id,
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {date(run.from)} – {date(run.to)}
                                    </Link>
                                </TableCell>
                                <TableCell className="whitespace-nowrap">
                                    {date(run.paid_on)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {run.transactions_count}
                                </TableCell>
                                <TableCell className="text-right">
                                    <Amount
                                        value={run.total}
                                        currency={currency}
                                    />
                                </TableCell>
                                <TableCell>
                                    <DeleteButton
                                        url={DataImportController.destroy.url(
                                            run.id,
                                        )}
                                        title={`Delete the run ${date(run.from)} – ${date(run.to)}?`}
                                        description={`Its ${run.transactions_count} transactions are deleted. Periods that included them change accordingly.`}
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </Panel>
    );
}
