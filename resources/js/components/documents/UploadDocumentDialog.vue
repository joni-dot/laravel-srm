<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { RouteFormDefinition } from '@/wayfinder';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    action: RouteFormDefinition<'post'>;
}>();

const isOpen = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="action"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing, progress }"
            >
                <DialogHeader>
                    <DialogTitle>Upload document</DialogTitle>
                </DialogHeader>

                <FormField
                    id="document-file"
                    label="File"
                    hint="PDF, JPG, PNG, Word (.docx) or Excel (.xlsx), up to 20 MB"
                    :error="errors.file"
                >
                    <input
                        id="document-file"
                        type="file"
                        name="file"
                        required
                        accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
                        class="text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:font-medium"
                    />
                </FormField>

                <progress
                    v-if="progress"
                    :value="progress.percentage"
                    max="100"
                    class="w-full"
                >
                    {{ progress.percentage }}%
                </progress>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Upload
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
