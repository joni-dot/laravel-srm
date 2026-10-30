<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RouteFormDefinition } from '@/wayfinder';
import ReminderDaysInput from '@/components/certificates/ReminderDaysInput.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { ContractFormData, ContractFormOptions } from '@/types';

const DEFAULT_REMINDER_DAYS = [60, 14];

const props = defineProps<{
    action: RouteFormDefinition<'post'>;
    cancelHref: string;
    submitLabel: string;
    options: ContractFormOptions;
    contract?: ContractFormData;
}>();

const contractTypeId = ref(props.contract?.contract_type_id ?? '');
const status = ref(props.contract?.status ?? 'active');
const ownerUserId = ref(props.contract?.owner_user_id ?? '');
const endsOn = ref(props.contract?.ends_on ?? '');
const autoRenews = ref(props.contract?.auto_renews ?? false);
const currencyCode = ref(props.contract?.currency_code ?? '');
const valuePeriod = ref(props.contract?.value_period ?? '');
const notes = ref(props.contract?.notes ?? '');
const reminderDays = ref<number[]>(
    props.contract?.reminder_days ?? DEFAULT_REMINDER_DAYS,
);

const reminderTarget = computed(() =>
    autoRenews.value ? 'the last day to cancel' : 'the end date',
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
</script>

<template>
    <Form
        v-bind="action"
        class="max-w-3xl space-y-10"
        v-slot="{ errors, processing }"
    >
        <section class="space-y-4">
            <h2 class="text-base font-medium">Basic information</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="title"
                    label="Title"
                    :error="errors.title"
                    class="sm:col-span-2"
                >
                    <Input
                        id="title"
                        name="title"
                        :default-value="contract?.title"
                        required
                        autofocus
                    />
                </FormField>
                <FormField
                    id="contract_number"
                    label="Contract number"
                    :error="errors.contract_number"
                >
                    <Input
                        id="contract_number"
                        name="contract_number"
                        :default-value="contract?.contract_number ?? ''"
                    />
                </FormField>
                <FormField
                    id="contract_type_id"
                    label="Type"
                    :error="errors.contract_type_id"
                >
                    <NativeSelect
                        id="contract_type_id"
                        v-model="contractTypeId"
                        name="contract_type_id"
                        required
                    >
                        <option value="" disabled>Choose a type</option>
                        <option
                            v-for="type in options.contractTypes"
                            :key="type.id"
                            :value="type.id"
                        >
                            {{ type.name }}
                        </option>
                    </NativeSelect>
                </FormField>
                <FormField id="status" label="Status" :error="errors.status">
                    <NativeSelect id="status" v-model="status" name="status">
                        <option
                            v-for="option in options.statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </FormField>
                <FormField
                    id="owner_user_id"
                    label="Contract owner"
                    hint="Gets the reminders. If empty, they go to the responsible buyer."
                    :error="errors.owner_user_id"
                >
                    <NativeSelect
                        id="owner_user_id"
                        v-model="ownerUserId"
                        name="owner_user_id"
                    >
                        <option value="">Nobody yet</option>
                        <option
                            v-for="owner in options.owners"
                            :key="owner.id"
                            :value="owner.id"
                        >
                            {{ owner.name }}
                        </option>
                    </NativeSelect>
                </FormField>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-base font-medium">Dates and renewal</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="starts_on"
                    label="Start date"
                    :error="errors.starts_on"
                >
                    <Input
                        id="starts_on"
                        type="date"
                        name="starts_on"
                        :default-value="contract?.starts_on ?? ''"
                    />
                </FormField>
                <FormField
                    id="ends_on"
                    label="End date"
                    hint="Leave empty if it runs until further notice"
                    :error="errors.ends_on"
                >
                    <Input
                        id="ends_on"
                        v-model="endsOn"
                        type="date"
                        name="ends_on"
                    />
                </FormField>
            </div>

            <Label class="flex items-center gap-2 font-normal">
                <Checkbox v-model="autoRenews" name="auto_renews" value="1" />
                Renews automatically unless cancelled
            </Label>

            <div v-if="autoRenews" class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="renewal_term_months"
                    label="Renews for (months)"
                    :error="errors.renewal_term_months"
                >
                    <Input
                        id="renewal_term_months"
                        type="number"
                        name="renewal_term_months"
                        min="1"
                        max="120"
                        required
                        :default-value="contract?.renewal_term_months ?? 12"
                    />
                </FormField>
                <FormField
                    id="notice_period_days"
                    label="Notice period (days)"
                    hint="How long before the end date it must be cancelled"
                    :error="errors.notice_period_days"
                >
                    <Input
                        id="notice_period_days"
                        type="number"
                        name="notice_period_days"
                        min="0"
                        max="730"
                        :default-value="contract?.notice_period_days ?? 90"
                    />
                </FormField>
            </div>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium">Email reminders</legend>
                <p v-if="!endsOn" class="text-sm text-muted-foreground">
                    Set an end date to get reminders.
                </p>
                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        Counted back from {{ reminderTarget }}.
                    </p>
                    <ReminderDaysInput
                        v-model="reminderDays"
                        unit-label="days before"
                    />
                </template>
                <InputError :message="firstReminderError(errors)" />
            </fieldset>
        </section>

        <section class="space-y-4">
            <h2 class="text-base font-medium">Value</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField id="value" label="Amount" :error="errors.value">
                    <Input
                        id="value"
                        type="number"
                        name="value"
                        min="0"
                        step="0.01"
                        :default-value="contract?.value ?? ''"
                    />
                </FormField>
                <FormField
                    id="currency_code"
                    label="Currency"
                    :error="errors.currency_code"
                >
                    <NativeSelect
                        id="currency_code"
                        v-model="currencyCode"
                        name="currency_code"
                    >
                        <option value="">Not set</option>
                        <option
                            v-for="option in options.currencies"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </FormField>
                <FormField
                    id="value_period"
                    label="Period"
                    :error="errors.value_period"
                >
                    <NativeSelect
                        id="value_period"
                        v-model="valuePeriod"
                        name="value_period"
                    >
                        <option value="">Not set</option>
                        <option
                            v-for="option in options.valuePeriods"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </FormField>
            </div>
        </section>

        <FormField id="notes" label="Notes" :error="errors.notes">
            <Textarea
                id="notes"
                v-model="notes"
                name="notes"
                rows="4"
                maxlength="5000"
            />
        </FormField>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ submitLabel }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="cancelHref">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
