<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import type { RouteFormDefinition } from '@/wayfinder';
import { computed, ref } from 'vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { SupplierFormData, SupplierFormOptions } from '@/types';

const props = defineProps<{
    action: RouteFormDefinition<'post'>;
    cancelHref: string;
    submitLabel: string;
    options: SupplierFormOptions;
    supplier?: SupplierFormData;
}>();

const status = ref(props.supplier?.status ?? 'prospect');
const countryCode = ref(props.supplier?.country_code ?? 'FI');
const currencyCode = ref(props.supplier?.currency_code ?? '');
const deliveryTerm = ref(props.supplier?.delivery_term ?? '');
const notes = ref(props.supplier?.notes ?? '');

const businessIdHint = computed(() =>
    countryCode.value === 'FI' ? 'Finnish business ID, e.g. 1234567-8' : '',
);

function isInCategory(categoryId: number): boolean {
    return props.supplier?.category_ids.includes(categoryId) ?? false;
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
                    id="name"
                    label="Name"
                    :error="errors.name"
                    class="sm:col-span-2"
                >
                    <Input
                        id="name"
                        name="name"
                        :default-value="supplier?.name"
                        required
                        autofocus
                    />
                </FormField>
                <FormField
                    id="supplier_number"
                    label="Supplier number"
                    hint="Your own ID, e.g. from your ERP"
                    :error="errors.supplier_number"
                >
                    <Input
                        id="supplier_number"
                        name="supplier_number"
                        :default-value="supplier?.supplier_number ?? ''"
                    />
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
            </div>

            <fieldset v-if="options.categories.length > 0" class="space-y-3">
                <legend class="text-sm font-medium">Categories</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    <Label
                        v-for="category in options.categories"
                        :key="category.id"
                        class="flex items-center gap-2 font-normal"
                    >
                        <Checkbox
                            name="category_ids[]"
                            :value="String(category.id)"
                            :default-value="isInCategory(category.id)"
                        />
                        {{ category.name }}
                    </Label>
                </div>
                <InputError :message="errors.category_ids" />
            </fieldset>
        </section>

        <section class="space-y-4">
            <h2 class="text-base font-medium">Company identification</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField
                    id="country_code"
                    label="Country"
                    :error="errors.country_code"
                >
                    <NativeSelect
                        id="country_code"
                        v-model="countryCode"
                        name="country_code"
                        required
                    >
                        <option
                            v-for="option in options.countries"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </FormField>
                <FormField
                    id="business_id"
                    label="Business ID"
                    :hint="businessIdHint"
                    :error="errors.business_id"
                >
                    <Input
                        id="business_id"
                        name="business_id"
                        :default-value="supplier?.business_id ?? ''"
                    />
                </FormField>
                <FormField
                    id="vat_number"
                    label="VAT number"
                    :error="errors.vat_number"
                >
                    <Input
                        id="vat_number"
                        name="vat_number"
                        :default-value="supplier?.vat_number ?? ''"
                    />
                </FormField>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-base font-medium">Address and contact details</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField
                    id="street_address"
                    label="Street address"
                    :error="errors.street_address"
                    class="sm:col-span-3"
                >
                    <Input
                        id="street_address"
                        name="street_address"
                        autocomplete="off"
                        :default-value="supplier?.street_address ?? ''"
                    />
                </FormField>
                <FormField
                    id="postal_code"
                    label="Postal code"
                    :error="errors.postal_code"
                >
                    <Input
                        id="postal_code"
                        name="postal_code"
                        autocomplete="off"
                        :default-value="supplier?.postal_code ?? ''"
                    />
                </FormField>
                <FormField
                    id="city"
                    label="City"
                    :error="errors.city"
                    class="sm:col-span-2"
                >
                    <Input
                        id="city"
                        name="city"
                        autocomplete="off"
                        :default-value="supplier?.city ?? ''"
                    />
                </FormField>
                <FormField id="website" label="Website" :error="errors.website">
                    <Input
                        id="website"
                        name="website"
                        placeholder="example.com"
                        :default-value="supplier?.website ?? ''"
                    />
                </FormField>
                <FormField id="email" label="Email" :error="errors.email">
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        :default-value="supplier?.email ?? ''"
                    />
                </FormField>
                <FormField id="phone" label="Phone" :error="errors.phone">
                    <Input
                        id="phone"
                        type="tel"
                        name="phone"
                        autocomplete="off"
                        :default-value="supplier?.phone ?? ''"
                    />
                </FormField>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-base font-medium">Commercial terms</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="payment_terms"
                    label="Payment terms"
                    :error="errors.payment_terms"
                >
                    <Input
                        id="payment_terms"
                        name="payment_terms"
                        placeholder="30 days net"
                        :default-value="supplier?.payment_terms ?? ''"
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
                    id="delivery_term"
                    label="Delivery term (Incoterm)"
                    :error="errors.delivery_term"
                >
                    <NativeSelect
                        id="delivery_term"
                        v-model="deliveryTerm"
                        name="delivery_term"
                    >
                        <option value="">Not set</option>
                        <option
                            v-for="option in options.deliveryTerms"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </FormField>
                <FormField
                    id="delivery_place"
                    label="Delivery place"
                    hint="e.g. Helsinki warehouse"
                    :error="errors.delivery_place"
                >
                    <Input
                        id="delivery_place"
                        name="delivery_place"
                        :default-value="supplier?.delivery_place ?? ''"
                    />
                </FormField>
            </div>
        </section>

        <section class="space-y-4">
            <FormField id="notes" label="Notes" :error="errors.notes">
                <Textarea
                    id="notes"
                    v-model="notes"
                    name="notes"
                    rows="4"
                    maxlength="5000"
                />
            </FormField>
        </section>

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
