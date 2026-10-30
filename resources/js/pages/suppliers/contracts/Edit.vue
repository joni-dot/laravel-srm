<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import ContractController from '@/actions/App/Http/Controllers/Suppliers/ContractController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import ContractForm from '@/components/contracts/ContractForm.vue';
import Heading from '@/components/Heading.vue';
import type { ContractFormData, ContractFormOptions } from '@/types';

const props = defineProps<
    ContractFormOptions & {
        supplier: { id: number; name: string };
        contract: ContractFormData;
    }
>();

const route = { supplier: props.supplier.id, contract: props.contract.id };

setLayoutProps({
    breadcrumbs: [
        { title: 'Suppliers', href: SupplierController.index() },
        {
            title: props.supplier.name,
            href: SupplierController.show(props.supplier.id),
        },
        { title: props.contract.title, href: ContractController.show(route) },
        { title: 'Edit', href: ContractController.edit(route) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${contract.title}`" />

    <div class="p-4">
        <Heading
            :title="`Edit ${contract.title}`"
            :description="supplier.name"
        />
        <ContractForm
            :action="ContractController.update.form(route)"
            :cancel-href="ContractController.show.url(route)"
            submit-label="Save changes"
            :options="props"
            :contract="contract"
        />
    </div>
</template>
