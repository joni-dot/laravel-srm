<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import RatingCriterionController from '@/actions/App/Http/Controllers/Admin/RatingCriterionController';
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
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    criterion?: { id: number; name: string };
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const action = props.criterion
    ? RatingCriterionController.update.form(props.criterion.id)
    : RatingCriterionController.store.form();
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
                        {{ criterion ? 'Rename criterion' : 'Add criterion' }}
                    </DialogTitle>
                </DialogHeader>

                <FormField
                    id="criterion-name"
                    label="Name"
                    :error="errors.name"
                >
                    <Input
                        id="criterion-name"
                        name="name"
                        :default-value="criterion?.name"
                        required
                        maxlength="100"
                        autocomplete="off"
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
