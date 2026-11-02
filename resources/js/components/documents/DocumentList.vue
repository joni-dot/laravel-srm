<script setup lang="ts">
import { Download, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import type { RouteFormDefinition } from '@/wayfinder';
import DocumentController from '@/actions/App/Http/Controllers/Suppliers/DocumentController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import UploadDocumentDialog from '@/components/documents/UploadDocumentDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatFileSize } from '@/lib/format';
import type { SupplierDocument } from '@/types';

defineProps<{
    title: string;
    supplierId: number;
    documents: SupplierDocument[];
    uploadAction: RouteFormDefinition<'post'>;
    canEdit: boolean;
}>();

const isUploading = ref(false);
const documentBeingDeleted = ref<SupplierDocument | null>(null);
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <Heading variant="small" :title="title" />
            <Button
                v-if="canEdit"
                variant="outline"
                size="sm"
                @click="isUploading = true"
            >
                <Upload /> Upload document
            </Button>
        </div>

        <p v-if="documents.length === 0" class="text-sm text-muted-foreground">
            No documents yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="document in documents"
                :key="document.id"
                class="flex items-center justify-between gap-4 px-4 py-2 text-sm"
            >
                <a
                    :href="
                        DocumentController.show.url({
                            supplier: supplierId,
                            document: document.id,
                        })
                    "
                    class="inline-flex min-w-0 items-center gap-2 hover:underline"
                >
                    <Download class="size-4 shrink-0" />
                    <span class="truncate">{{ document.file_name }}</span>
                    <span class="shrink-0 text-muted-foreground"
                        >({{ formatFileSize(document.file_size) }})</span
                    >
                </a>
                <Button
                    v-if="canEdit"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Delete ${document.file_name}`"
                    @click="documentBeingDeleted = document"
                >
                    <Trash2 />
                </Button>
            </li>
        </ul>
    </section>

    <UploadDocumentDialog
        v-if="isUploading"
        :action="uploadAction"
        :open="true"
        @update:open="isUploading = false"
    />

    <ConfirmActionDialog
        v-if="documentBeingDeleted"
        :action="
            DocumentController.destroy.form({
                supplier: supplierId,
                document: documentBeingDeleted.id,
            })
        "
        :title="`Delete ${documentBeingDeleted.file_name}?`"
        description="The file is removed permanently."
        confirm-label="Delete"
        :open="true"
        @update:open="documentBeingDeleted = null"
    />
</template>
