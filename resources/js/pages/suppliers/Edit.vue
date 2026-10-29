<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import Heading from '@/components/Heading.vue';
import SupplierForm from '@/components/suppliers/SupplierForm.vue';
import type { SupplierFormData, SupplierFormOptions } from '@/types';

const props = defineProps<
    SupplierFormOptions & {
        supplier: SupplierFormData;
    }
>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Suppliers', href: SupplierController.index() },
        {
            title: props.supplier.name,
            href: SupplierController.show(props.supplier.id),
        },
        { title: 'Edit', href: SupplierController.edit(props.supplier.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${supplier.name}`" />

    <div class="p-4">
        <Heading :title="`Edit ${supplier.name}`" />
        <SupplierForm
            :action="SupplierController.update.form(supplier.id)"
            :cancel-href="SupplierController.show.url(supplier.id)"
            submit-label="Save changes"
            :options="props"
            :supplier="supplier"
        />
    </div>
</template>
