import type { ReactNode } from 'react';
import { Amount } from '@/components/amount';
import { Panel } from '@/components/page';
import { SegmentedBar } from '@/components/segmented-bar';
import { cn } from '@/lib/utils';

export type Flow = {
    gross: string;
    distribution_fee: string;
    payment_fee: string;
    other_deductions: string;
    received_with_breakdown: string;
    received_without_breakdown: string;
    received: string;
    lines_with_breakdown: number;
    lines_without_breakdown: number;
};

/** What the label does with the money once it has arrived. */
export type Shares = {
    expense: string;
    payee_share: string;
    label_share: string;
};

function Row({
    label,
    hint,
    value,
    currency,
    strong = false,
}: {
    label: string;
    hint?: ReactNode;
    value: string | number;
    currency?: string;
    strong?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex items-baseline justify-between gap-4 px-4 py-2 text-sm',
                strong && 'border-t bg-muted/40 font-semibold',
            )}
        >
            <div className="min-w-0">
                <div>{label}</div>
                {hint && (
                    <div className="text-xs font-normal text-muted-foreground">
                        {hint}
                    </div>
                )}
            </div>
            <Amount value={value} currency={currency} />
        </div>
    );
}

function percent(part: string, whole: string): string | undefined {
    const share = Number(whole) === 0 ? null : Number(part) / Number(whole);

    return share === null
        ? undefined
        : `${(share * 100).toLocaleString('en-GB', { maximumFractionDigits: 1 })}% of what customers paid`;
}

const negative = (value: string): number => -Math.abs(Number(value));

/**
 * From what customers paid to what the label keeps: the distributor's
 * share, payment fees, and, where the page knows them, costs and what is
 * owed to payees.
 */
export function MoneyFlow({
    flow,
    shares,
    currency,
}: {
    flow: Flow;
    shares?: Shares;
    currency?: string;
}) {
    const hasBreakdown = flow.lines_with_breakdown > 0;

    if (!hasBreakdown && !shares) {
        return null;
    }

    return (
        <Panel
            title="Where the money went"
            description="From what customers paid to what the label keeps. Deductions are known only for statements that report them."
            flush
        >
            {shares && Number(flow.received) > 0 && (
                <div className="border-b p-4">
                    <SegmentedBar
                        format={(value) => (
                            <Amount value={value} currency={currency} />
                        )}
                        segments={[
                            {
                                label: 'Owed to payees',
                                value: Number(shares.payee_share),
                            },
                            {
                                label: 'Costs',
                                value: Math.abs(Number(shares.expense)),
                            },
                            {
                                label: 'Kept by the label',
                                value: Number(shares.label_share),
                            },
                        ]}
                    />
                </div>
            )}
            <div data-money-flow className="divide-y">
                {hasBreakdown && (
                    <>
                        <Row
                            label="Paid by customers"
                            hint={`${flow.lines_with_breakdown.toLocaleString('en-GB')} lines with a breakdown`}
                            value={flow.gross}
                            currency={currency}
                        />
                        <Row
                            label="Kept by distributors, shops and agencies"
                            hint={percent(flow.distribution_fee, flow.gross)}
                            value={negative(flow.distribution_fee)}
                            currency={currency}
                        />
                        <Row
                            label="Kept by payment providers"
                            hint={percent(flow.payment_fee, flow.gross)}
                            value={negative(flow.payment_fee)}
                            currency={currency}
                        />
                        {Math.abs(Number(flow.other_deductions)) >= 0.005 && (
                            <Row
                                label="Other deductions"
                                hint="Taxes, shipping, mechanicals passed on and payout fees"
                                value={-Number(flow.other_deductions)}
                                currency={currency}
                            />
                        )}
                    </>
                )}
                {flow.lines_without_breakdown > 0 && (
                    <Row
                        label="Received without a breakdown"
                        hint={`${flow.lines_without_breakdown.toLocaleString('en-GB')} lines whose statement does not say what was deducted before`}
                        value={flow.received_without_breakdown}
                        currency={currency}
                    />
                )}
                <Row
                    label="Received by the label"
                    value={flow.received}
                    currency={currency}
                    strong
                />
                {shares && (
                    <>
                        <Row
                            label="Costs"
                            hint="Expenses paid in this period"
                            value={shares.expense}
                            currency={currency}
                        />
                        <Row
                            label="Owed to payees"
                            hint="Their shares under the contracts, before earlier balances and payouts"
                            value={-Number(shares.payee_share)}
                            currency={currency}
                        />
                        <Row
                            label="Kept by the label"
                            value={shares.label_share}
                            currency={currency}
                            strong
                        />
                    </>
                )}
            </div>
        </Panel>
    );
}
