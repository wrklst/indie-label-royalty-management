import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { SelectInput } from '@/components/field';
import { Button } from '@/components/ui/button';
import type { Option } from '@/types';

export type ArtistCredit = { artist_id: number | ''; role: string };

const ROLES = [
    { value: 'primary', label: 'Main artist' },
    { value: 'featuring', label: 'Featuring' },
    { value: 'remixer', label: 'Remixer' },
    { value: 'producer', label: 'Producer' },
];

/**
 * Rows of additional artist credits. Controlled when `onChange` is given;
 * otherwise it keeps its own rows and submits them with the enclosing
 * form as `artists[n][artist_id]` / `artists[n][role]`.
 */
export function ArtistCreditRows({
    value,
    onChange,
    artistOptions,
    defaultValue = [],
}: {
    value?: ArtistCredit[];
    onChange?: (rows: ArtistCredit[]) => void;
    artistOptions: Option[];
    defaultValue?: ArtistCredit[];
}) {
    const [own, setOwn] = useState<ArtistCredit[]>(defaultValue);
    const controlled = value !== undefined && onChange !== undefined;
    const rows = controlled ? value : own;
    const update = (next: ArtistCredit[]) =>
        controlled ? onChange(next) : setOwn(next);

    return (
        <div className="grid gap-2">
            {!controlled && <input type="hidden" name="artists" value="" />}
            {rows.map((row, position) => (
                <div
                    key={position}
                    className="grid grid-cols-[1fr_9rem_2.25rem] gap-2"
                >
                    <SelectInput
                        aria-label="Credited artist"
                        name={
                            controlled
                                ? undefined
                                : `artists[${position}][artist_id]`
                        }
                        value={row.artist_id}
                        onChange={(event) =>
                            update(
                                rows.map((current, index) =>
                                    index === position
                                        ? {
                                              ...current,
                                              artist_id: event.target.value
                                                  ? Number(event.target.value)
                                                  : '',
                                          }
                                        : current,
                                ),
                            )
                        }
                        options={artistOptions}
                        placeholder="Choose artist…"
                        required
                    />
                    <SelectInput
                        aria-label="Credit role"
                        name={
                            controlled
                                ? undefined
                                : `artists[${position}][role]`
                        }
                        value={row.role}
                        onChange={(event) =>
                            update(
                                rows.map((current, index) =>
                                    index === position
                                        ? {
                                              ...current,
                                              role: event.target.value,
                                          }
                                        : current,
                                ),
                            )
                        }
                    >
                        {ROLES.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </SelectInput>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Remove credit"
                        onClick={() =>
                            update(
                                rows.filter((_, index) => index !== position),
                            )
                        }
                    >
                        <X />
                    </Button>
                </div>
            ))}
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        update([...rows, { artist_id: '', role: 'featuring' }])
                    }
                >
                    <Plus />
                    Add artist credit
                </Button>
            </div>
        </div>
    );
}
