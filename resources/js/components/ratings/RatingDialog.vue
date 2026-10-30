<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import RatingController from '@/actions/App/Http/Controllers/Suppliers/RatingController';
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
import { Textarea } from '@/components/ui/textarea';
import type { RatingCriterion } from '@/types';

const SCORES = [
    { value: 5, label: '5 – Excellent' },
    { value: 4, label: '4 – Good' },
    { value: 3, label: '3 – Acceptable' },
    { value: 2, label: '2 – Poor' },
    { value: 1, label: '1 – Very poor' },
];

const props = defineProps<{
    supplierId: number;
    criteria: RatingCriterion[];
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const scores = reactive<Record<number, string>>(
    Object.fromEntries(props.criteria.map((criterion) => [criterion.id, ''])),
);
const comment = ref('');

function todayAsDate(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <Form
                v-bind="RatingController.store.form(supplierId)"
                :options="{ preserveScroll: true }"
                @success="isOpen = false"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Add rating</DialogTitle>
                    <DialogDescription>
                        The overall score is the average of these scores.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        v-for="criterion in criteria"
                        :id="`score-${criterion.id}`"
                        :key="criterion.id"
                        :label="criterion.name"
                        :error="errors[`scores.${criterion.id}`]"
                    >
                        <NativeSelect
                            :id="`score-${criterion.id}`"
                            v-model="scores[criterion.id]"
                            :name="`scores[${criterion.id}]`"
                            required
                        >
                            <option value="" disabled>Choose a score</option>
                            <option
                                v-for="score in SCORES"
                                :key="score.value"
                                :value="score.value"
                            >
                                {{ score.label }}
                            </option>
                        </NativeSelect>
                    </FormField>
                </div>

                <FormField
                    id="rating-date"
                    label="Rating date"
                    :error="errors.rated_on"
                >
                    <Input
                        id="rating-date"
                        type="date"
                        name="rated_on"
                        :default-value="todayAsDate()"
                        required
                    />
                </FormField>

                <FormField
                    id="rating-comment"
                    label="Comment"
                    :error="errors.comment"
                >
                    <Textarea
                        id="rating-comment"
                        v-model="comment"
                        name="comment"
                        rows="3"
                        maxlength="5000"
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
