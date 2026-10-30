<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import RiskOverrideController from '@/actions/App/Http/Controllers/Suppliers/RiskOverrideController';
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
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { RiskLevel, SelectOption } from '@/types';

const props = defineProps<{
    supplierId: number;
    currentLevel: RiskLevel;
    currentReason: string | null;
    riskLevels: SelectOption[];
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const level = ref<string>(props.currentLevel);
const reason = ref(props.currentReason ?? '');
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="RiskOverrideController.update.form(supplierId)"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Change risk level</DialogTitle>
                    <DialogDescription>
                        This replaces the calculated level until someone
                        switches it back.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id="risk-level"
                    label="Risk level"
                    :error="errors.level"
                >
                    <NativeSelect
                        id="risk-level"
                        v-model="level"
                        name="level"
                        required
                    >
                        <option
                            v-for="option in riskLevels"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </FormField>

                <FormField
                    id="risk-reason"
                    label="Reason"
                    :error="errors.reason"
                >
                    <Textarea
                        id="risk-reason"
                        v-model="reason"
                        name="reason"
                        rows="3"
                        required
                        maxlength="1000"
                    />
                </FormField>

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
