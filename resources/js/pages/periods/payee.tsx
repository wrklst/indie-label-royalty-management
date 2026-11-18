import { Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import PeriodController from '@/actions/App/Http/Controllers/PeriodController';
import { Page } from '@/components/page';
import { StatementView } from '@/components/statement-view';
import { Button } from '@/components/ui/button';
import { show as showPayee } from '@/routes/payees';
import { index, show } from '@/routes/periods';
import type { StatementData } from '@/types';

type Props = {
    period: { id: number; name: string };
    payee: { id: number; name: string };
    statement: StatementData | null;
};

export default function PeriodPayee({ period, payee, statement }: Props) {
    return (
        <Page
            title={`${payee.name} · ${period.name}`}
            description="Live figures. They change as you import, edit contracts or record payouts; generate a statement to freeze them."
            actions={
                <>
                    <Button variant="outline" asChild>
                        <Link href={show(period.id)}>Back to period</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={showPayee(payee.id)}>Payee</Link>
                    </Button>
                    {statement && (
                        <Button
                            onClick={() =>
                                router.post(
                                    PeriodController.statements.url(period.id),
                                    { payee_id: payee.id },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <FileText />
                            Generate statement
                        </Button>
                    )}
                </>
            }
        >
            {statement ? (
                <StatementView data={statement} />
            ) : (
                <p className="text-sm text-muted-foreground">
                    Nothing earned, transferred or carried over for this payee
                    up to {period.name}.
                </p>
            )}
        </Page>
    );
}

PeriodPayee.layout = {
    breadcrumbs: [
        { title: 'Periods', href: index() },
        { title: 'Payee breakdown', href: '#' },
    ],
};
