import { Link, usePage } from '@inertiajs/react';
import { Building2, LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { show as businessShow } from '@/routes/business';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

type SidebarBusiness = {
    id: string;
    name: string;
};

function BusinessesNav({ businesses }: { businesses: SidebarBusiness[] }) {
    const { url } = usePage();

    if (businesses.length === 0) {
        return null;
    }

    return (
        <SidebarGroup>
            <SidebarGroupLabel>Businesses</SidebarGroupLabel>
            <SidebarMenu>
                {businesses.map((business) => {
                    const href = businessShow.url({
                        business: business.id,
                    });
                    const isActive = url.startsWith(
                        `/businesses/${business.id}`,
                    );

                    return (
                        <SidebarMenuItem key={business.id}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive}
                                tooltip={business.name}
                            >
                                <Link href={href} prefetch>
                                    <Building2 />
                                    <span>{business.name}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function AppSidebar() {
    const { props } = usePage<{ businesses?: SidebarBusiness[] }>();
    const businesses = props.businesses ?? [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <BusinessesNav businesses={businesses} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
