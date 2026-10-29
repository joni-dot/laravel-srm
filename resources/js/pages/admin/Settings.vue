<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: SettingsController.edit() }],
    },
});

defineProps<{
    reminderEmail: string | null;
}>();
</script>

<template>
    <Head title="Settings" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading title="Settings" class="mb-0" />

        <Form
            v-bind="SettingsController.update.form()"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <FormField
                id="reminder_email"
                label="Reminder email"
                hint="Certificate reminders for suppliers without a responsible buyer go here, e.g. a shared procurement mailbox."
                :error="errors.reminder_email"
            >
                <Input
                    id="reminder_email"
                    type="email"
                    name="reminder_email"
                    :default-value="reminderEmail ?? ''"
                    placeholder="procurement@example.com"
                    autocomplete="off"
                />
            </FormField>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Save
            </Button>
        </Form>
    </div>
</template>
