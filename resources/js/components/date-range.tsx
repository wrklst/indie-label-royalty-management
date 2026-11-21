import { SelectInput, Field } from '@/components/field';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/hooks/use-filters';

type Values = {
    from?: string | null;
    to?: string | null;
    date?: string | null;
};

/**
 * From and to dates, and which date they count: the sale date or the paid
 * date (`date=paid`). The one control for every page with a date range.
 *
 * With a `url` it filters that list as the dates are typed. Without one it
 * is two fields of the surrounding form, labelled "Paid from" and "Paid
 * until" for a royalty tool, which always counts paid dates.
 *
 * Native date fields show dates the way the browser is set to; only the
 * rest of the page is fixed to one format.
 */
export function DateRange(
    props:
        | {
              url: string;
              filters: object;
              /** What an empty range means, for a page that does not show all time. */
              note?: string;
              /** Offer the choice between sale date and paid date. */
              byDate?: boolean;
          }
        | {
              url?: undefined;
              defaults: Values;
              errors: Record<string, string>;
              required?: boolean;
          },
) {
    if (props.url === undefined) {
        return <RangeFields {...props} />;
    }

    return <LiveRange {...props} />;
}

function LiveRange({
    url,
    filters,
    note,
    byDate = true,
}: {
    url: string;
    filters: object;
    note?: string;
    byDate?: boolean;
}) {
    const current = filters as Values &
        Record<string, string | number | boolean | null | undefined>;
    const { apply } = useFilters(url, current);

    return (
        <div className="flex flex-wrap items-center gap-3">
            {byDate && (
                <SelectInput
                    aria-label="Date used"
                    value={current.date ?? ''}
                    onChange={(event) => apply({ date: event.target.value })}
                    className="w-40"
                >
                    <option value="">By sale date</option>
                    <option value="paid">By paid date</option>
                </SelectInput>
            )}
            <Input
                aria-label="From"
                type="date"
                key={`from-${current.from ?? ''}`}
                defaultValue={current.from ?? ''}
                onChange={(event) => apply({ from: event.target.value }, true)}
                className="w-40"
            />
            <span className="text-sm text-muted-foreground">to</span>
            <Input
                aria-label="To"
                type="date"
                key={`to-${current.to ?? ''}`}
                defaultValue={current.to ?? ''}
                onChange={(event) => apply({ to: event.target.value }, true)}
                className="w-40"
            />
            {note && (
                <span className="text-xs text-muted-foreground">{note}</span>
            )}
        </div>
    );
}

function RangeFields({
    defaults,
    errors,
    required = true,
}: {
    defaults: Values;
    errors: Record<string, string>;
    required?: boolean;
}) {
    return (
        <>
            <Field
                label="Paid from"
                htmlFor="from"
                error={errors.from}
                required={required}
            >
                <Input
                    id="from"
                    name="from"
                    type="date"
                    defaultValue={defaults.from ?? ''}
                    required={required}
                />
            </Field>
            <Field
                label="Paid until"
                htmlFor="to"
                error={errors.to}
                required={required}
            >
                <Input
                    id="to"
                    name="to"
                    type="date"
                    defaultValue={defaults.to ?? ''}
                    required={required}
                />
            </Field>
        </>
    );
}
