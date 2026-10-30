<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ContractTypeController from '@/actions/App/Http/Controllers/Admin/ContractTypeController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import ContractTypeDialog from '@/components/contracts/ContractTypeDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

type ContractTypeRow = {
    id: number;
    name: string;
    contracts_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Contract types', href: ContractTypeController.index() },
        ],
    },
});

defineProps<{
    contractTypes: ContractTypeRow[];
}>();

const isAdding = ref(false);
const typeBeingRenamed = ref<ContractTypeRow | null>(null);
const typeBeingDeleted = ref<ContractTypeRow | null>(null);

function contractCount(type: ContractTypeRow): string {
    return type.contracts_count === 1
        ? '1 contract'
        : `${type.contracts_count} contracts`;
}
</script>

<template>
    <Head title="Contract types" />

    <div class="flex max-w-3xl flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Contract types"
                description="The kinds of contracts buyers can record"
                class="mb-0"
            />
            <Button @click="isAdding = true"><Plus /> Add type</Button>
        </div>

        <p
            v-if="contractTypes.length === 0"
            class="text-sm text-muted-foreground"
        >
            No contract types yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="type in contractTypes"
                :key="type.id"
                class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
            >
                <div>
                    <p class="font-medium">{{ type.name }}</p>
                    <p class="text-muted-foreground">
                        {{ contractCount(type) }}
                    </p>
                </div>
                <div class="flex">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Rename ${type.name}`"
                        @click="typeBeingRenamed = type"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        v-if="type.contracts_count === 0"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Delete ${type.name}`"
                        @click="typeBeingDeleted = type"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>
    </div>

    <ContractTypeDialog
        v-if="isAdding"
        :open="true"
        @update:open="isAdding = false"
    />

    <ContractTypeDialog
        v-if="typeBeingRenamed"
        :contract-type="typeBeingRenamed"
        :open="true"
        @update:open="typeBeingRenamed = null"
    />

    <ConfirmActionDialog
        v-if="typeBeingDeleted"
        :action="ContractTypeController.destroy.form(typeBeingDeleted.id)"
        :title="`Delete ${typeBeingDeleted.name}?`"
        description="No contracts use this type, so nothing else is affected."
        confirm-label="Delete"
        :open="true"
        @update:open="typeBeingDeleted = null"
    />
</template>
