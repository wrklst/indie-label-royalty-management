import type { ComponentProps } from 'react';
import { Field, SelectInput } from '@/components/field';

/** What may be declared about AI in a recording, composition, lyrics or artwork. */
export const AI_LEVELS = {
    none: 'None',
    partly: 'Partly AI-generated',
    all: 'Fully AI-generated',
} as const;

/**
 * The "AI in the …" select. Pass `value` and `onChange` for a controlled
 * form, or `defaultValue` for a plain one.
 */
export function AiField({
    label,
    name,
    error,
    ...props
}: {
    label: string;
    name: string;
    error?: string;
} & Omit<ComponentProps<typeof SelectInput>, 'children' | 'name' | 'id'>) {
    return (
        <Field label={label} htmlFor={name} error={error}>
            <SelectInput
                id={name}
                name={name}
                placeholder="Not declared"
                {...props}
            >
                {Object.entries(AI_LEVELS).map(([value, text]) => (
                    <option key={value} value={value}>
                        {text}
                    </option>
                ))}
            </SelectInput>
        </Field>
    );
}
