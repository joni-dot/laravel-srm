<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import ArchiveSupplierController from '@/actions/App/Http/Controllers/Suppliers/ArchiveSupplierController';
import RestoreSupplierController from '@/actions/App/Http/Controllers/Suppliers/RestoreSupplierController';
import DocumentController from '@/actions/App/Http/Controllers/Suppliers/DocumentController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import RatingList from '@/components/ratings/RatingList.vue';
import SupplierRiskPanel from '@/components/risk/SupplierRiskPanel.vue';
import SpendList from '@/components/spend/SpendList.vue';
import CertificateList from '@/components/certificates/CertificateList.vue';
import CommentSection from '@/components/comments/CommentSection.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import ContractList from '@/components/contracts/ContractList.vue';
import DocumentList from '@/components/documents/DocumentList.vue';
import ContactList from '@/components/suppliers/ContactList.vue';
import DetailItem from '@/components/suppliers/DetailItem.vue';
import SupplierStatusBadge from '@/components/suppliers/SupplierStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    CertificateType,
    CommentFeed,
    RatingCriterion,
    SelectOption,
    SupplierDetails,
} from '@/types';

const props = defineProps<{
    supplier: SupplierDetails;
    comments: CommentFeed;
    certificateTypes: CertificateType[];
    ratingCriteria: RatingCriterion[];
    riskLevels: SelectOption[];
    currencies: SelectOption[];
    can: {
        update: boolean;
        archive: boolean;
        restore: boolean;
        comment: boolean;
        viewContracts: boolean;
        viewSpend: boolean;
    };
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

const contractCount = computed(() =>
    props.supplier.contracts_count === 1
        ? '1 contract'
        : `${props.supplier.contracts_count} contracts`,
);

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

        <div
            v-if="supplier.missing_certificates.length > 0"
            role="alert"
            class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"
        >
            <TriangleAlert class="size-5 shrink-0" />
            <p>
                Missing a valid required certificate:
                <strong>{{ supplier.missing_certificates.join(', ') }}</strong>
            </p>
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
            <DetailItem
                label="Responsible buyer"
                :value="supplier.responsible_user"
            />
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

        <SupplierRiskPanel
            :supplier-id="supplier.id"
            :risk="supplier.risk"
            :risk-levels="riskLevels"
            :can-edit="can.update"
        />

        <RatingList
            :supplier-id="supplier.id"
            :latest-score="supplier.latest_rating_score"
            :ratings="supplier.ratings"
            :criteria="ratingCriteria"
            :can-edit="can.update"
        />

        <SpendList
            v-if="can.viewSpend"
            :supplier-id="supplier.id"
            :spends="supplier.spends"
            :currencies="currencies"
            :can-edit="can.update"
        />

        <CertificateList
            :supplier-id="supplier.id"
            :certificates="supplier.certificates"
            :certificate-types="certificateTypes"
            :can-edit="can.update"
        />

        <ContractList
            v-if="can.viewContracts"
            :supplier-id="supplier.id"
            :contracts="supplier.contracts"
            :can-edit="can.update"
        />
        <section v-else class="space-y-2">
            <Heading variant="small" title="Contracts" />
            <p class="text-sm text-muted-foreground">
                {{ contractCount }}. Only buyers and admins can see the details.
            </p>
        </section>

        <DocumentList
            title="Documents"
            :supplier-id="supplier.id"
            :documents="supplier.documents"
            :upload-action="DocumentController.store.form(supplier.id)"
            :can-edit="can.update"
        />

        <ContactList
            :supplier-id="supplier.id"
            :contacts="supplier.contacts"
            :can-edit="can.update"
        />

        <CommentSection
            :supplier-id="supplier.id"
            :feed="comments"
            :can-comment="can.comment"
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
