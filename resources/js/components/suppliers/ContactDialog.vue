<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import ContactController from '@/actions/App/Http/Controllers/Suppliers/ContactController';
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
import type { Contact } from '@/types';

const props = defineProps<{
    supplierId: number;
    contact?: Contact;
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const action = props.contact
    ? ContactController.update.form({
          supplier: props.supplierId,
          contact: props.contact.id,
      })
    : ContactController.store.form(props.supplierId);
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
                        {{ contact ? 'Edit contact' : 'Add contact' }}
                    </DialogTitle>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="contact-name"
                        label="Name"
                        :error="errors.name"
                    >
                        <Input
                            id="contact-name"
                            name="name"
                            :default-value="contact?.name"
                            required
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        id="contact-job-title"
                        label="Job title"
                        :error="errors.job_title"
                    >
                        <Input
                            id="contact-job-title"
                            name="job_title"
                            :default-value="contact?.job_title ?? ''"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        id="contact-email"
                        label="Email"
                        :error="errors.email"
                    >
                        <Input
                            id="contact-email"
                            type="email"
                            name="email"
                            :default-value="contact?.email ?? ''"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        id="contact-phone"
                        label="Phone"
                        :error="errors.phone"
                    >
                        <Input
                            id="contact-phone"
                            type="tel"
                            name="phone"
                            :default-value="contact?.phone ?? ''"
                            autocomplete="off"
                        />
                    </FormField>
                </div>

                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox
                        name="is_primary"
                        value="1"
                        :default-value="contact?.is_primary ?? false"
                    />
                    Primary contact
                </Label>

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
