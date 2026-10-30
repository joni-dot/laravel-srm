<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import ContractController from '@/actions/App/Http/Controllers/Suppliers/ContractController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import ContractForm from '@/components/contracts/ContractForm.vue';
import Heading from '@/components/Heading.vue';
import type { ContractFormOptions } from '@/types';

const props = defineProps<
    ContractFormOptions & {
        supplier: { id: number; name: string };
    }
>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Suppliers', href: SupplierController.index() },
        {
            title: props.supplier.name,
            href: SupplierController.show(props.supplier.id),
        },
        {
            title: 'New contract',
            href: ContractController.create(props.supplier.id),
        },
    ],
});
</script>

<template>
    <Head title="New contract" />

    <div class="p-4">
        <Heading title="New contract" :description="supplier.name" />
        <ContractForm
            :action="ContractController.store.form(supplier.id)"
            :cancel-href="SupplierController.show.url(supplier.id)"
            submit-label="Create contract"
            :options="props"
        />
    </div>
</template>
