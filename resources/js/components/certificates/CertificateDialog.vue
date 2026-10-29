<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import CertificateController from '@/actions/App/Http/Controllers/Suppliers/CertificateController';
import ReminderDaysInput from '@/components/certificates/ReminderDaysInput.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { Certificate, CertificateType } from '@/types';

const DEFAULT_REMINDER_DAYS = [60, 14];

const props = defineProps<{
    supplierId: number;
    certificateTypes: CertificateType[];
    certificate?: Certificate;
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const certificateTypeId = ref(props.certificate?.certificate_type_id ?? '');
const expiresOn = ref(props.certificate?.expires_on ?? '');
const reminderDays = ref<number[]>(
    props.certificate?.reminder_days ?? DEFAULT_REMINDER_DAYS,
);

/**
 * Errors arrive per reminder ("reminder_days.1"), but one message is enough here.
 */
function firstReminderError(
    errors: Record<string, string>,
): string | undefined {
    return Object.entries(errors).find(([field]) =>
        field.startsWith('reminder_days'),
    )?.[1];
}

const action = props.certificate
    ? CertificateController.update.form({
          supplier: props.supplierId,
          certificate: props.certificate.id,
      })
    : CertificateController.store.form(props.supplierId);
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-xl">
            <Form
                v-bind="action"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing, progress }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            certificate ? 'Edit certificate' : 'Add certificate'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Reminders are emailed to the supplier's responsible
                        buyer.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="certificate-type"
                        label="Type"
                        :error="errors.certificate_type_id"
                    >
                        <NativeSelect
                            id="certificate-type"
                            v-model="certificateTypeId"
                            name="certificate_type_id"
                            required
                        >
                            <option value="" disabled>Choose a type</option>
                            <option
                                v-for="type in certificateTypes"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }}
                            </option>
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="certificate-reference"
                        label="Certificate number"
                        :error="errors.reference"
                    >
                        <Input
                            id="certificate-reference"
                            name="reference"
                            :default-value="certificate?.reference ?? ''"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        id="certificate-issued-on"
                        label="Issue date"
                        :error="errors.issued_on"
                    >
                        <Input
                            id="certificate-issued-on"
                            type="date"
                            name="issued_on"
                            :default-value="certificate?.issued_on ?? ''"
                        />
                    </FormField>
                    <FormField
                        id="certificate-expires-on"
                        label="Expiry date"
                        hint="Leave empty if it never expires"
                        :error="errors.expires_on"
                    >
                        <Input
                            id="certificate-expires-on"
                            v-model="expiresOn"
                            type="date"
                            name="expires_on"
                        />
                    </FormField>
                </div>

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">Email reminders</legend>
                    <p v-if="!expiresOn" class="text-sm text-muted-foreground">
                        Set an expiry date to get reminders.
                    </p>
                    <ReminderDaysInput v-else v-model="reminderDays" />
                    <InputError :message="firstReminderError(errors)" />
                </fieldset>

                <FormField
                    id="certificate-file"
                    label="File"
                    :hint="
                        certificate?.file_name
                            ? `Current file: ${certificate.file_name}. Choose a new file to replace it.`
                            : 'PDF, JPG or PNG, up to 10 MB'
                    "
                    :error="errors.file"
                >
                    <input
                        id="certificate-file"
                        type="file"
                        name="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
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
                        Save
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
