<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CertificateTypeController from '@/actions/App/Http/Controllers/Admin/CertificateTypeController';
import CertificateTypeDialog from '@/components/certificates/CertificateTypeDialog.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type CertificateTypeRow = {
    id: number;
    name: string;
    is_required: boolean;
    certificates_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Certificate types',
                href: CertificateTypeController.index(),
            },
        ],
    },
});

defineProps<{
    certificateTypes: CertificateTypeRow[];
}>();

const isAdding = ref(false);
const typeBeingEdited = ref<CertificateTypeRow | null>(null);
const typeBeingDeleted = ref<CertificateTypeRow | null>(null);

function certificateCount(type: CertificateTypeRow): string {
    return type.certificates_count === 1
        ? '1 certificate'
        : `${type.certificates_count} certificates`;
}
</script>

<template>
    <Head title="Certificate types" />

    <div class="flex max-w-3xl flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Certificate types"
                description="The kinds of certificates buyers can record, and which ones every active supplier must have"
                class="mb-0"
            />
            <Button @click="isAdding = true"><Plus /> Add type</Button>
        </div>

        <p
            v-if="certificateTypes.length === 0"
            class="text-sm text-muted-foreground"
        >
            No certificate types yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="type in certificateTypes"
                :key="type.id"
                class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
            >
                <div>
                    <p class="font-medium">
                        {{ type.name }}
                        <Badge v-if="type.is_required" class="ml-1"
                            >Required</Badge
                        >
                    </p>
                    <p class="text-muted-foreground">
                        {{ certificateCount(type) }}
                    </p>
                </div>
                <div class="flex">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Edit ${type.name}`"
                        @click="typeBeingEdited = type"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        v-if="type.certificates_count === 0"
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

    <CertificateTypeDialog
        v-if="isAdding"
        :open="true"
        @update:open="isAdding = false"
    />

    <CertificateTypeDialog
        v-if="typeBeingEdited"
        :certificate-type="typeBeingEdited"
        :open="true"
        @update:open="typeBeingEdited = null"
    />

    <ConfirmActionDialog
        v-if="typeBeingDeleted"
        :action="CertificateTypeController.destroy.form(typeBeingDeleted.id)"
        :title="`Delete ${typeBeingDeleted.name}?`"
        description="No certificates use this type, so nothing else is affected."
        confirm-label="Delete"
        :open="true"
        @update:open="typeBeingDeleted = null"
    />
</template>
