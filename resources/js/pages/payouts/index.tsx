import { Link } from '@inertiajs/react';
import PayoutController from '@/actions/App/Http/Controllers/PayoutController';
import { Amount } from '@/components/amount';
import { DeleteButton } from '@/components/delete-button';
import { EmptyState, Page, Panel } from '@/components/page';
import { Pagination } from '@/components/pagination';
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
import { show as showPayee } from '@/routes/payees';
import { index } from '@/routes/payouts';
import { index as periods, show as showPeriod } from '@/routes/periods';
import type { Paginated } from '@/types';

type Payout = {
    id: number;
    total: string;
    memo: string | null;
    payee_id: number;
    period_id: number;
    paid_on: string;
    payee: string | null;
    period: string | null;
    user: string | null;
    ledgers: { name: string; amount: string }[];
};

export default function Payouts({ payouts }: { payouts: Paginated<Payout> }) {
    return (
        <Page
            title="Payouts"
            description="Payments you have made to payees. A payout is recorded from its period and reduces what the payee is still owed. Nothing is sent to a bank from here."
        >
            {payouts.total === 0 ? (
                <EmptyState
                    title="No payouts recorded"
                    description="Open a period and record a payout next to the payee you have paid."
                    action={
                        <Button asChild>
                            <Link href={periods()}>Go to periods</Link>
                        </Button>
                    }
                />
            ) : (
                <Panel flush>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Paid on</TableHead>
                                <TableHead>Payee</TableHead>
                                <TableHead>Period</TableHead>
                                <TableHead>Booked to</TableHead>
                                <TableHead>Memo</TableHead>
                                <TableHead>Recorded by</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead className="w-12" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payouts.data.map((payout) => (
                                <TableRow key={payout.id}>
                                    <TableCell className="whitespace-nowrap">
                                        {date(payout.paid_on)}
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            href={showPayee(payout.payee_id)}
                                            className="font-medium underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {payout.payee}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            href={showPeriod(payout.period_id)}
                                            className="underline decoration-muted-foreground/40 underline-offset-4"
                                        >
                                            {payout.period}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {payout.ledgers.length === 1
                                            ? payout.ledgers[0].name
                                            : payout.ledgers
                                                  .map(
                                                      (ledger) =>
                                                          `${ledger.name} ${Number(ledger.amount).toFixed(2)}`,
                                                  )
                                                  .join(', ')}
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate text-muted-foreground">
                                        {payout.memo}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {payout.user ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Amount value={payout.total} />
                                    </TableCell>
                                    <TableCell>
                                        <DeleteButton
                                            url={PayoutController.destroy.url(
                                                payout.id,
                                            )}
                                            title="Delete this payout?"
                                            description={`The ${Number(payout.total).toFixed(2)} paid to ${payout.payee} for ${payout.period} becomes payable again. Statements already generated keep showing it until you regenerate them.`}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination page={payouts} noun="payouts" />
                </Panel>
            )}
        </Page>
    );
}

Payouts.layout = { breadcrumbs: [{ title: 'Payouts', href: index() }] };
