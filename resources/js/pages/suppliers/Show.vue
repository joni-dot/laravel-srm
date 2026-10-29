<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import ArchiveSupplierController from '@/actions/App/Http/Controllers/Suppliers/ArchiveSupplierController';
import RestoreSupplierController from '@/actions/App/Http/Controllers/Suppliers/RestoreSupplierController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import ContactList from '@/components/suppliers/ContactList.vue';
import DetailItem from '@/components/suppliers/DetailItem.vue';
import SupplierStatusBadge from '@/components/suppliers/SupplierStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { SupplierDetails } from '@/types';

const props = defineProps<{
    supplier: SupplierDetails;
    can: { update: boolean; archive: boolean; restore: boolean };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Suppliers', href: SupplierController.index() },
        {
            title: props.supplier.name,
            href: SupplierController.show(props.supplier.id),
        },
    ],
});

const isConfirmingArchive = ref(false);

const address = computed(() =>
    [
        props.supplier.street_address,
        [props.supplier.postal_code, props.supplier.city]
            .filter(Boolean)
            .join(' '),
        props.supplier.country,
    ]
        .filter(Boolean)
        .join(', '),
);

const deliveryTerm = computed(() =>
    [props.supplier.delivery_term, props.supplier.delivery_place]
        .filter(Boolean)
        .join(', '),
);

const archivedOn = computed(() =>
    props.supplier.archived_at
        ? new Date(props.supplier.archived_at).toLocaleDateString()
        : null,
);

function restore(): void {
    router.post(
        RestoreSupplierController.url(props.supplier.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="supplier.name" />

    <div class="flex max-w-5xl flex-col gap-8 p-4">
        <div
            v-if="archivedOn"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            This supplier was archived on {{ archivedOn }}. Restore it to make
            changes.
        </div>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Heading
                    :title="supplier.name"
                    :description="
                        supplier.supplier_number
                            ? `Supplier number ${supplier.supplier_number}`
                            : undefined
                    "
                    class="mb-0"
                />
                <div class="flex flex-wrap gap-2">
                    <SupplierStatusBadge :status="supplier.status" />
                    <Badge
                        v-for="category in supplier.categories"
                        :key="category"
                        variant="outline"
                    >
                        {{ category }}
                    </Badge>
                </div>
            </div>

            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="SupplierController.edit(supplier.id)">
                        <Pencil /> Edit
                    </Link>
                </Button>
                <Button
                    v-if="can.archive"
                    variant="outline"
                    @click="isConfirmingArchive = true"
                >
                    <Archive /> Archive
                </Button>
                <Button v-if="can.restore" variant="outline" @click="restore">
                    <ArchiveRestore /> Restore
                </Button>
            </div>
        </div>

        <dl
            class="grid gap-6 rounded-xl border p-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <DetailItem label="Business ID" :value="supplier.business_id" />
            <DetailItem label="VAT number" :value="supplier.vat_number" />
            <DetailItem label="Address" :value="address" />
            <DetailItem label="Website">
                <a
                    v-if="supplier.website"
                    :href="supplier.website"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="break-all hover:underline"
                >
                    {{ supplier.website }}
                </a>
                <template v-else>—</template>
            </DetailItem>
            <DetailItem label="Email">
                <a
                    v-if="supplier.email"
                    :href="`mailto:${supplier.email}`"
                    class="break-all hover:underline"
                >
                    {{ supplier.email }}
                </a>
                <template v-else>—</template>
            </DetailItem>
            <DetailItem label="Phone">
                <a
                    v-if="supplier.phone"
                    :href="`tel:${supplier.phone}`"
                    class="hover:underline"
                >
                    {{ supplier.phone }}
                </a>
                <template v-else>—</template>
            </DetailItem>
            <DetailItem label="Payment terms" :value="supplier.payment_terms" />
            <DetailItem label="Currency" :value="supplier.currency" />
            <DetailItem label="Delivery term" :value="deliveryTerm" />
            <DetailItem
                v-if="supplier.notes"
                label="Notes"
                class="sm:col-span-2 lg:col-span-3"
            >
                <p class="whitespace-pre-line">{{ supplier.notes }}</p>
            </DetailItem>
        </dl>

        <ContactList
            :supplier-id="supplier.id"
            :contacts="supplier.contacts"
            :can-edit="can.update"
        />
    </div>

    <ConfirmActionDialog
        v-model:open="isConfirmingArchive"
        :action="ArchiveSupplierController.form(supplier.id)"
        :title="`Archive ${supplier.name}?`"
        description="It will be hidden from the supplier list, but nothing is deleted. You can restore it at any time."
        confirm-label="Archive"
    />
</template>
