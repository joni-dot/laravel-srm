<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    FileText,
    LayoutGrid,
    Settings,
    Tags,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import CertificateTypeController from '@/actions/App/Http/Controllers/Admin/CertificateTypeController';
import ContractTypeController from '@/actions/App/Http/Controllers/Admin/ContractTypeController';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Suppliers',
        href: SupplierController.index(),
        icon: Building2,
    },
];

const adminNavItems = computed<NavItem[]>(() => {
    const can = page.props.auth.can;

    const items: { isVisible: boolean; item: NavItem }[] = [
        {
            isVisible: can.manageCategories,
            item: {
                title: 'Categories',
                href: CategoryController.index(),
                icon: Tags,
            },
        },
        {
            isVisible: can.manageCertificateTypes,
            item: {
                title: 'Certificate types',
                href: CertificateTypeController.index(),
                icon: BadgeCheck,
            },
        },
        {
            isVisible: can.manageContractTypes,
            item: {
                title: 'Contract types',
                href: ContractTypeController.index(),
                icon: FileText,
            },
        },
        {
            isVisible: can.manageUsers,
            item: { title: 'Users', href: UserController.index(), icon: Users },
        },
        {
            isVisible: can.manageSettings,
            item: {
                title: 'Settings',
                href: SettingsController.edit(),
                icon: Settings,
            },
        },
    ];

    return items.filter(({ isVisible }) => isVisible).map(({ item }) => item);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Platform" :items="mainNavItems" />
            <NavMain
                v-if="adminNavItems.length > 0"
                label="Administration"
                :items="adminNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
