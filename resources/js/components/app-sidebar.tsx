import { usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Banknote,
    BookOpenText,
    CalendarRange,
    Disc3,
    FileSignature,
    FileText,
    LayoutGrid,
    LibraryBig,
    ListMusic,
    Mic2,
    Package,
    TrendingUp,
    Upload,
    Users,
} from 'lucide-react';
import { LabelSwitcher } from '@/components/label-switcher';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as artists } from '@/routes/artists';
import { index as catalog } from '@/routes/catalog';
import { index as contracts } from '@/routes/contracts';
import { index as dataImports } from '@/routes/data-imports';
import { index as items } from '@/routes/items';
import { index as ledgers } from '@/routes/ledgers';
import { index as payees } from '@/routes/payees';
import { index as payouts } from '@/routes/payouts';
import { index as periods } from '@/routes/periods';
import { index as portal } from '@/routes/portal';
import { index as profitability } from '@/routes/profitability';
import { index as releases } from '@/routes/releases';
import { index as statements } from '@/routes/statements';
import { index as tracks } from '@/routes/tracks';
import { index as transactions } from '@/routes/transactions';

const adminGroups: NavGroup[] = [
    {
        title: 'Overview',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Royalties',
        items: [
            { title: 'Data imports', href: dataImports(), icon: Upload },
            {
                title: 'Transactions',
                href: transactions(),
                icon: ArrowLeftRight,
            },
            { title: 'Periods', href: periods(), icon: CalendarRange },
            { title: 'Statements', href: statements(), icon: FileText },
            { title: 'Payouts', href: payouts(), icon: Banknote },
            {
                title: 'Profitability',
                href: profitability(),
                icon: TrendingUp,
            },
        ],
    },
    {
        title: 'Catalog',
        items: [
            { title: 'Catalog manager', href: catalog(), icon: LibraryBig },
            { title: 'Releases', href: releases(), icon: Disc3 },
            { title: 'Tracks', href: tracks(), icon: ListMusic },
            { title: 'Artists', href: artists(), icon: Mic2 },
            { title: 'Items', href: items(), icon: Package },
            { title: 'Contracts', href: contracts(), icon: FileSignature },
            { title: 'Payees', href: payees(), icon: Users },
            { title: 'Ledgers', href: ledgers(), icon: BookOpenText },
        ],
    },
];

const payeeGroups: NavGroup[] = [
    {
        title: 'Royalties',
        items: [{ title: 'Overview', href: portal(), icon: FileText }],
    },
];

export function AppSidebar() {
    const { label } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <LabelSwitcher />
            </SidebarHeader>

            <SidebarContent>
                {label && (
                    <NavMain
                        groups={
                            label.role === 'payee' ? payeeGroups : adminGroups
                        }
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
