<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, LayoutGrid, Tags, Users } from '@lucide/vue';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
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

const mainNavItems = computed<NavItem[]>(() => [
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
    ...(page.props.auth.can.manageCategories
        ? [
              {
                  title: 'Categories',
                  href: CategoryController.index(),
                  icon: Tags,
              },
          ]
        : []),
    ...(page.props.auth.can.manageUsers
        ? [{ title: 'Users', href: UserController.index(), icon: Users }]
        : []),
]);
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
