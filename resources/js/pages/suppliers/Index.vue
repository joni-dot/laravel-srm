<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Plus, Search, TriangleAlert } from '@lucide/vue';
import { reactive, watch } from 'vue';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import SupplierStatusBadge from '@/components/suppliers/SupplierStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type {
    Category,
    Paginated,
    SelectOption,
    SupplierListItem,
    SupplierStatus,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Suppliers', href: SupplierController.index() }],
    },
});

const props = defineProps<{
    suppliers: Paginated<SupplierListItem>;
    filters: {
        search: string;
        status: SupplierStatus | null;
        category: number | null;
        archived: boolean;
        missing_certificates: boolean;
        contracts_action_soon: boolean;
    };
    statuses: SelectOption[];
    categories: Category[];
    can: { create: boolean; viewContracts: boolean };
}>();

const filters = reactive({
    search: props.filters.search,
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
    archived: props.filters.archived,
    missingCertificates: props.filters.missing_certificates,
    contractsActionSoon: props.filters.contracts_action_soon,
});

function applyFilters(): void {
    router.get(
        SupplierController.index.url(),
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            category: filters.category || undefined,
            archived: filters.archived ? 1 : undefined,
            missing_certificates: filters.missingCertificates ? 1 : undefined,
            contracts_action_soon: filters.contractsActionSoon ? 1 : undefined,
        },
        {
            only: ['suppliers', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(() => filters.search, useDebounceFn(applyFilters, 300));
watch(
    () => [
        filters.status,
        filters.category,
        filters.archived,
        filters.missingCertificates,
        filters.contractsActionSoon,
    ],
    applyFilters,
);
</script>

<template>
    <Head title="Suppliers" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="filters.archived ? 'Archived suppliers' : 'Suppliers'"
                description="Find suppliers by name, supplier number, business ID or VAT number"
                class="mb-0"
            />
            <Button v-if="can.create" as-child>
                <Link :href="SupplierController.create()">
                    <Plus /> New supplier
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Search suppliers"
                    aria-label="Search suppliers"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.status"
                aria-label="Filter by status"
                class="w-44"
            >
                <option value="">All statuses</option>
                <option
                    v-for="status in statuses"
                    :key="status.value"
                    :value="status.value"
                >
                    {{ status.label }}
                </option>
            </NativeSelect>
            <NativeSelect
                v-model="filters.category"
                aria-label="Filter by category"
                class="w-52"
            >
                <option value="">All categories</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </NativeSelect>
            <Label class="flex items-center gap-2 font-normal">
                <Checkbox v-model="filters.missingCertificates" />
                Missing certificates
            </Label>
            <Label
                v-if="can.viewContracts"
                class="flex items-center gap-2 font-normal"
            >
                <Checkbox v-model="filters.contractsActionSoon" />
                Contracts needing action
            </Label>
            <Label class="flex items-center gap-2 font-normal">
                <Checkbox v-model="filters.archived" />
                Show archived
            </Label>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Name</th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Supplier no.
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Location
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Categories
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="supplier in suppliers.data"
                        :key="supplier.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="SupplierController.show(supplier.id)"
                                class="hover:underline"
                            >
                                {{ supplier.name }}
                            </Link>
                            <p
                                v-if="supplier.missing_certificates.length > 0"
                                class="mt-1 flex items-center gap-1 text-xs font-normal text-red-600 dark:text-red-400"
                            >
                                <TriangleAlert class="size-3.5 shrink-0" />
                                Missing:
                                {{ supplier.missing_certificates.join(', ') }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ supplier.supplier_number }}
                        </td>
                        <td class="px-4 py-3">
                            {{
                                [supplier.city, supplier.country]
                                    .filter(Boolean)
                                    .join(', ')
                            }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ supplier.categories.join(', ') }}
                        </td>
                        <td class="px-4 py-3">
                            <SupplierStatusBadge :status="supplier.status" />
                        </td>
                    </tr>
                    <tr v-if="suppliers.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No suppliers found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="suppliers" />
    </div>
</template>
