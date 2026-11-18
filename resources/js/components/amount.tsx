import { usePage } from '@inertiajs/react';
import { isNegative, money } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * A money value in the label currency; negatives are shown in red.
 */
export function Amount({
    value,
    currency,
    precise = false,
    className,
    muted = false,
}: {
    value: string | number | null | undefined;
    currency?: string;
    precise?: boolean;
    className?: string;
    muted?: boolean;
}) {
    const { label } = usePage().props;
    const zero = Number(value) === 0;

    return (
        <span
            className={cn(
                'tabular-nums',
                isNegative(value) && 'text-red-600 dark:text-red-400',
                (muted || zero) && 'text-muted-foreground',
                className,
            )}
        >
            {money(value, currency ?? label?.currency ?? 'EUR', precise)}
        </span>
    );
}
