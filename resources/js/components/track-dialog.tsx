import { Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import TrackController from '@/actions/App/Http/Controllers/TrackController';
import { FormDialog } from '@/components/form-dialog';
import { TrackFields } from '@/components/track-fields';
import type { EditableTrack } from '@/components/track-fields';
import { Button } from '@/components/ui/button';
import type { Option } from '@/types';

/**
 * The one dialog a track is edited in: from the tracks list, the release
 * page and the track page. Without a `trigger` it is the pencil of a table row.
 */
export function TrackDialog({
    track,
    artistOptions,
    contractOptions,
    inheritedContract,
    trigger,
}: {
    track: EditableTrack;
    artistOptions: Option[];
    contractOptions: Option[];
    inheritedContract?: Option | null;
    trigger?: ReactNode;
}) {
    return (
        <FormDialog
            title={`Edit ${track.name}`}
            form={TrackController.update.form(track.id)}
            submitLabel="Save track"
            trigger={
                trigger ?? (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Edit ${track.name}`}
                    >
                        <Pencil className="text-muted-foreground" />
                    </Button>
                )
            }
        >
            {(errors) => (
                <TrackFields
                    track={track}
                    artistOptions={artistOptions}
                    contractOptions={contractOptions}
                    inheritedContract={inheritedContract}
                    errors={errors}
                />
            )}
        </FormDialog>
    );
}
