<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import SpendController from '@/actions/App/Http/Controllers/Suppliers/SpendController';
import FormField from '@/components/FormField.vue';
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
import type { SelectOption, SupplierSpend } from '@/types';

const props = defineProps<{
    supplierId: number;
    currencies: SelectOption[];
    spend?: SupplierSpend;
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const currentYear = new Date().getFullYear();
const currencyCode = ref(props.spend?.currency_code ?? 'EUR');
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="SpendController.store.form(supplierId)"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            spend ? `Edit spend for ${spend.year}` : 'Add spend'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        One amount per year. Saving a year again replaces its
                        amount.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        id="spend-year"
                        label="Year"
                        :error="errors.year"
                    >
                        <Input
                            id="spend-year"
                            type="number"
                            name="year"
                            min="2000"
                            :max="currentYear"
                            required
                            :readonly="Boolean(spend)"
                            :default-value="spend?.year ?? currentYear"
                        />
                    </FormField>
                    <FormField
                        id="spend-amount"
                        label="Amount"
                        :error="errors.amount"
                    >
                        <Input
                            id="spend-amount"
                            type="number"
                            name="amount"
                            min="0"
                            step="0.01"
                            required
                            :default-value="spend?.amount ?? ''"
                        />
                    </FormField>
                    <FormField
                        id="spend-currency"
                        label="Currency"
                        :error="errors.currency_code"
                    >
                        <NativeSelect
                            id="spend-currency"
                            v-model="currencyCode"
                            name="currency_code"
                            required
                        >
                            <option
                                v-for="option in currencies"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.value }}
                            </option>
                        </NativeSelect>
                    </FormField>
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
