<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ContractController from '@/actions/App/Http/Controllers/Suppliers/ContractController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import UploadContractDocumentController from '@/actions/App/Http/Controllers/Suppliers/UploadContractDocumentController';
import CommentSection from '@/components/comments/CommentSection.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import ContractHealthBadge from '@/components/contracts/ContractHealthBadge.vue';
import DocumentList from '@/components/documents/DocumentList.vue';
import Heading from '@/components/Heading.vue';
import DetailItem from '@/components/suppliers/DetailItem.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/format';
import type { CommentFeed, ContractDetails } from '@/types';

const props = defineProps<{
    supplier: { id: number; name: string };
    contract: ContractDetails;
    comments: CommentFeed;
    can: { update: boolean; comment: boolean };
}>();

const route = { supplier: props.supplier.id, contract: props.contract.id };

setLayoutProps({
    breadcrumbs: [
        { title: 'Suppliers', href: SupplierController.index() },
        {
            title: props.supplier.name,
            href: SupplierController.show(props.supplier.id),
        },
        { title: props.contract.title, href: ContractController.show(route) },
    ],
});

const isConfirmingDelete = ref(false);

function optionalDate(date: string | null): string | null {
    return date ? formatDate(date) : null;
}

const value = computed(() => {
    const { value, currency_code, value_period } = props.contract;

    return value && currency_code
        ? `${formatMoney(value, currency_code)} ${value_period ?? ''}`
        : null;
});

const renewal = computed(() => {
    const { auto_renews, renewal_term_months } = props.contract;

    return auto_renews
        ? `Renews automatically for ${renewal_term_months} months at a time`
        : 'Does not renew automatically';
});

const reminders = computed(() => {
    const days = props.contract.reminder_days;

    return days.length > 0
        ? `${days.join(' and ')} days before`
        : 'No reminders';
});
</script>

<template>
    <Head :title="contract.title" />

    <div class="flex max-w-5xl flex-col gap-8 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Heading
                    :title="contract.title"
                    :description="`${contract.type} with ${supplier.name}`"
                    class="mb-0"
                />
                <ContractHealthBadge :health="contract.health" />
            </div>

            <div v-if="can.update" class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="ContractController.edit(route)">
                        <Pencil /> Edit
                    </Link>
                </Button>
                <Button variant="outline" @click="isConfirmingDelete = true">
                    <Trash2 /> Delete
                </Button>
            </div>
        </div>

        <dl
            class="grid gap-6 rounded-xl border p-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <DetailItem
                label="Contract number"
                :value="contract.contract_number"
            />
            <DetailItem label="Contract owner" :value="contract.owner" />
            <DetailItem label="Value" :value="value" />
            <DetailItem
                label="Start date"
                :value="optionalDate(contract.starts_on)"
            />
            <DetailItem
                label="End date"
                :value="optionalDate(contract.ends_on)"
            />
            <DetailItem label="Renewal" :value="renewal" />
            <DetailItem
                v-if="contract.auto_renews"
                label="Last day to cancel"
                :value="optionalDate(contract.action_by)"
            />
            <DetailItem
                v-if="contract.auto_renews"
                label="Notice period"
                :value="
                    contract.notice_period_days === null
                        ? null
                        : `${contract.notice_period_days} days`
                "
            />
            <DetailItem label="Email reminders" :value="reminders" />
            <DetailItem
                v-if="contract.notes"
                label="Notes"
                class="sm:col-span-2 lg:col-span-3"
            >
                <p class="whitespace-pre-line">{{ contract.notes }}</p>
            </DetailItem>
        </dl>

        <DocumentList
            title="Documents"
            :supplier-id="supplier.id"
            :documents="contract.documents"
            :upload-action="UploadContractDocumentController.form(route)"
            :can-edit="can.update"
        />

        <CommentSection
            :supplier-id="supplier.id"
            :contract-id="contract.id"
            :feed="comments"
            :can-comment="can.comment"
        />
    </div>

    <ConfirmActionDialog
        v-model:open="isConfirmingDelete"
        :action="ContractController.destroy.form(route)"
        :title="`Delete ${contract.title}?`"
        description="The contract, its documents, comments and reminders are removed permanently. To keep the record, set its status to Terminated instead."
        confirm-label="Delete"
    />
</template>
