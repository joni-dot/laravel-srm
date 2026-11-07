<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import ApiClientController from '@/actions/App/Http/Controllers/Admin/ApiClientController';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { ApiClientRow, ApiScopeOption } from '@/types';

const props = defineProps<{
    apiClient?: ApiClientRow;
    scopes: ApiScopeOption[];
    responsibleUsers: { id: number; name: string }[];
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const responsibleUserId = ref(props.apiClient?.responsible_user_id ?? '');

const action = props.apiClient
    ? ApiClientController.update.form(props.apiClient.id)
    : ApiClientController.store.form();

function hasScope(scope: string): boolean {
    return props.apiClient?.scopes.includes(scope) ?? false;
}
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
                        {{ apiClient ? 'Edit API client' : 'Add API client' }}
                    </DialogTitle>
                    <DialogDescription>
                        A system that reads SRM data on its own, such as an ERP
                        or reporting tool.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id="api-client-name"
                    label="Name"
                    hint="Which system this is, e.g. ERP integration"
                    :error="errors.name"
                >
                    <Input
                        id="api-client-name"
                        name="name"
                        :default-value="apiClient?.name"
                        required
                        maxlength="100"
                        autocomplete="off"
                    />
                </FormField>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium">Access</legend>
                    <div
                        v-for="scope in scopes"
                        :key="scope.value"
                        class="space-y-1"
                    >
                        <Label class="flex items-center gap-2 font-normal">
                            <Checkbox
                                name="scopes[]"
                                :value="scope.value"
                                :default-value="hasScope(scope.value)"
                            />
                            {{ scope.label }}
                        </Label>
                        <p class="pl-6 text-sm text-muted-foreground">
                            {{ scope.description }}
                        </p>
                    </div>
                    <InputError :message="errors.scopes" />
                </fieldset>

                <FormField
                    id="api-client-responsible"
                    label="Responsible person"
                    hint="Who to ask about this integration"
                    :error="errors.responsible_user_id"
                >
                    <NativeSelect
                        id="api-client-responsible"
                        v-model="responsibleUserId"
                        name="responsible_user_id"
                        required
                    >
                        <option value="" disabled>Choose a person</option>
                        <option
                            v-for="user in responsibleUsers"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }}
                        </option>
                    </NativeSelect>
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
