import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

export function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
    className,
    required,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
    required?: boolean;
}) {
    return (
        <div className={cn('grid content-start gap-1.5', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {required && <span className="text-red-600"> *</span>}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

const CHEVRON =
    "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E\")";

/**
 * A native select styled like the inputs. Native keeps long option lists
 * (releases, tracks) keyboard-searchable and fast.
 */
export function SelectInput({
    options,
    placeholder,
    className,
    children,
    ...props
}: ComponentProps<'select'> & {
    options?: (Option | string)[];
    placeholder?: string;
}) {
    return (
        <select
            style={{ backgroundImage: CHEVRON }}
            className={cn(
                'h-9 w-full min-w-0 appearance-none truncate rounded-md border border-input bg-transparent bg-[length:1rem] bg-[position:right_0.5rem_center] bg-no-repeat pr-8 pl-2.5 text-sm leading-[2.125rem] shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30',
                className,
            )}
            {...props}
        >
            {placeholder !== undefined && (
                <option value="">{placeholder}</option>
            )}
            {options?.map((option) =>
                typeof option === 'string' ? (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ) : (
                    <option key={option.id} value={option.id}>
                        {option.name}
                    </option>
                ),
            )}
            {children}
        </select>
    );
}

export function CheckField({
    label,
    hint,
    className,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & {
    label: string;
    hint?: ReactNode;
}) {
    return (
        <label className={cn('flex items-start gap-2.5 text-sm', className)}>
            <input
                type="checkbox"
                className="mt-0.5 size-4 rounded border-input accent-primary"
                {...props}
            />
            <span>
                <span className="font-medium">{label}</span>
                {hint && (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
        </label>
    );
}
