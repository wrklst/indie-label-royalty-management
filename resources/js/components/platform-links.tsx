import { router } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Radar } from 'lucide-react';
import { useEffect } from 'react';
import PlatformLinkController from '@/actions/App/Http/Controllers/PlatformLinkController';
import { DeleteButton } from '@/components/delete-button';
import { Field, SelectInput } from '@/components/field';
import { FormDialog } from '@/components/form-dialog';
import { Panel } from '@/components/page';
import { PlatformIcon } from '@/components/platform-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type PlatformLinksProps = {
    links: {
        id: number;
        platform: string;
        platform_name: string;
        url: string;
        origin: string;
    }[];
    detecting: boolean;
    platforms: { value: string; label: string }[];
};

/** How a link that was not typed in came to be here. */
const ORIGINS: Record<string, string> = {
    lookup: 'found by code',
    sales: 'from sales',
    guess: 'found',
    search: 'found by search',
};

function LinkFields({
    platforms,
    errors,
    link,
}: {
    platforms: PlatformLinksProps['platforms'];
    errors: Record<string, string>;
    link?: PlatformLinksProps['links'][number];
}) {
    return (
        <>
            <Field
                label="Link"
                htmlFor="platform_link_url"
                error={errors.url}
                required
            >
                <Input
                    id="platform_link_url"
                    name="url"
                    type="url"
                    defaultValue={link?.url}
                    placeholder="https://example.bandcamp.com/album/example-release"
                    required
                />
            </Field>
            <Field
                label="Platform"
                htmlFor="platform_link_platform"
                error={errors.platform}
                hint="Leave on automatic to have it recognised from the link."
            >
                <SelectInput
                    id="platform_link_platform"
                    name="platform"
                    defaultValue={link?.platform ?? ''}
                >
                    <option value="">Automatic</option>
                    {platforms.map((platform) => (
                        <option key={platform.value} value={platform.value}>
                            {platform.label}
                        </option>
                    ))}
                </SelectInput>
            </Field>
        </>
    );
}

/**
 * Where a release or a track is offered: links that were found, and
 * links added by hand.
 */
export function PlatformLinks({
    owner,
    platformLinks: { links, detecting, platforms },
    includesTracks = false,
}: {
    owner: { release_id: number } | { track_id: number };
    platformLinks: PlatformLinksProps;
    /** A release also looks its tracks up. */
    includesTracks?: boolean;
}) {
    useEffect(() => {
        if (!detecting) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['platformLinks'] }),
            4000,
        );

        return () => window.clearInterval(timer);
    }, [detecting]);

    return (
        <Panel
            title="Where to find it"
            description="Its pages on the platforms where it is streamed or sold. Found links can be corrected or removed; a removed one is not found again."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={detecting}
                        title={
                            includesTracks
                                ? 'Looks the release and each of its tracks up by UPC and ISRC, in Bandcamp sales, on Subvert and, with a web search login, with one billed search'
                                : 'Looks the track up by ISRC and in Bandcamp sales'
                        }
                        onClick={() =>
                            router.post(
                                PlatformLinkController.detect.url(),
                                owner,
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Radar />
                        {detecting ? 'Looking…' : 'Find links'}
                    </Button>
                    <FormDialog
                        title="Add a link"
                        form={PlatformLinkController.store.form()}
                        submitLabel="Save link"
                        trigger={
                            <Button variant="outline" size="sm">
                                <Plus />
                                Add link
                            </Button>
                        }
                    >
                        {(errors) => (
                            <>
                                {Object.entries(owner).map(([key, value]) => (
                                    <input
                                        key={key}
                                        type="hidden"
                                        name={key}
                                        value={value}
                                    />
                                ))}
                                <LinkFields
                                    platforms={platforms}
                                    errors={errors}
                                />
                            </>
                        )}
                    </FormDialog>
                </div>
            }
            flush
        >
            {links.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">
                    {detecting
                        ? 'Looking for links…'
                        : 'No links yet. Use Find links, or add one.'}
                </p>
            ) : (
                <ul data-platform-links className="divide-y">
                    {links.map((link) => (
                        <li
                            key={link.id}
                            className="flex items-center gap-3 px-4 py-2 text-sm"
                        >
                            <span className="flex w-40 shrink-0 items-center gap-2 font-medium">
                                <PlatformIcon
                                    platform={link.platform}
                                    name={link.platform_name}
                                />
                                {link.platform_name}
                            </span>
                            <a
                                href={link.url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex min-w-0 flex-1 items-center gap-1.5 text-muted-foreground hover:text-foreground"
                            >
                                <span className="truncate">{link.url}</span>
                                <ExternalLink className="size-3.5 shrink-0" />
                            </a>
                            {ORIGINS[link.origin] && (
                                <Badge variant="info" className="shrink-0">
                                    {ORIGINS[link.origin]}
                                </Badge>
                            )}
                            <div className="flex shrink-0">
                                <FormDialog
                                    title={`Edit the ${link.platform_name} link`}
                                    form={PlatformLinkController.update.form(
                                        link.id,
                                    )}
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Edit the ${link.platform_name} link`}
                                        >
                                            <Pencil className="text-muted-foreground" />
                                        </Button>
                                    }
                                >
                                    {(errors) => (
                                        <LinkFields
                                            platforms={platforms}
                                            errors={errors}
                                            link={link}
                                        />
                                    )}
                                </FormDialog>
                                <DeleteButton
                                    url={PlatformLinkController.destroy.url(
                                        link.id,
                                    )}
                                    title={`Remove the ${link.platform_name} link?`}
                                    description="A link that was found is not found again after you remove it. You can still add it by hand."
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
