<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import CertificateTypeController from '@/actions/App/Http/Controllers/Admin/CertificateTypeController';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type EditableType = { id: number; name: string; is_required: boolean };

const props = defineProps<{
    certificateType?: EditableType;
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const action = props.certificateType
    ? CertificateTypeController.update.form(props.certificateType.id)
    : CertificateTypeController.store.form();
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="action"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            certificateType
                                ? 'Edit certificate type'
                                : 'Add certificate type'
                        }}
                    </DialogTitle>
                </DialogHeader>

                <FormField
                    id="certificate-type-name"
                    label="Name"
                    :error="errors.name"
                >
                    <Input
                        id="certificate-type-name"
                        name="name"
                        :default-value="certificateType?.name"
                        placeholder="ISO 9001"
                        required
                        maxlength="100"
                        autocomplete="off"
                    />
                </FormField>

                <div class="space-y-1">
                    <Label class="flex items-center gap-2 font-normal">
                        <Checkbox
                            name="is_required"
                            value="1"
                            :default-value="
                                certificateType?.is_required ?? false
                            "
                        />
                        Required for all active suppliers
                    </Label>
                    <p class="text-sm text-muted-foreground">
                        Active suppliers without a valid certificate of this
                        type are flagged.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
