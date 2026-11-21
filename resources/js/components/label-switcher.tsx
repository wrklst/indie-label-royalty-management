import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Disc3, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { create, switchMethod } from '@/routes/labels';

/**
 * Shows the label you are working in and lets you switch to another.
 */
export function LabelSwitcher() {
    const { label, labels, name, canCreateLabel } = usePage().props;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg">
                            {label?.signet_url ? (
                                <img
                                    src={label.signet_url}
                                    alt=""
                                    data-label-signet
                                    className="aspect-square size-8 rounded-md object-contain"
                                />
                            ) : (
                                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                    <Disc3 className="size-5" />
                                </div>
                            )}
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {label?.name ?? name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {label
                                        ? `Royalties · ${label.currency}`
                                        : 'No label yet'}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Labels
                        </DropdownMenuLabel>
                        {labels.map((item) => (
                            <DropdownMenuItem
                                key={item.id}
                                onSelect={() =>
                                    item.id !== label?.id &&
                                    router.post(switchMethod.url(item.id))
                                }
                            >
                                <span className="truncate">{item.name}</span>
                                {item.id === label?.id && (
                                    <Check className="ml-auto size-4" />
                                )}
                            </DropdownMenuItem>
                        ))}
                        {canCreateLabel && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={create()}
                                        className="cursor-pointer"
                                    >
                                        <Plus className="size-4" />
                                        New label
                                    </Link>
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
