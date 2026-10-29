<script setup lang="ts">
import { Mail, Pencil, Phone, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ContactController from '@/actions/App/Http/Controllers/Suppliers/ContactController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import ContactDialog from '@/components/suppliers/ContactDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Contact } from '@/types';

defineProps<{
    supplierId: number;
    contacts: Contact[];
    canEdit: boolean;
}>();

const isAdding = ref(false);
const contactBeingEdited = ref<Contact | null>(null);
const contactBeingDeleted = ref<Contact | null>(null);
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <Heading variant="small" title="Contacts" />
            <Button
                v-if="canEdit"
                variant="outline"
                size="sm"
                @click="isAdding = true"
            >
                <Plus /> Add contact
            </Button>
        </div>

        <p v-if="contacts.length === 0" class="text-sm text-muted-foreground">
            No contacts yet.
        </p>

        <ul v-else class="grid gap-3 md:grid-cols-2">
            <li
                v-for="contact in contacts"
                :key="contact.id"
                class="space-y-1 rounded-lg border p-4 text-sm"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">
                            {{ contact.name }}
                            <Badge
                                v-if="contact.is_primary"
                                variant="secondary"
                                class="ml-1"
                                >Primary</Badge
                            >
                        </p>
                        <p
                            v-if="contact.job_title"
                            class="text-muted-foreground"
                        >
                            {{ contact.job_title }}
                        </p>
                    </div>
                    <div v-if="canEdit" class="flex shrink-0">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`Edit ${contact.name}`"
                            @click="contactBeingEdited = contact"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`Delete ${contact.name}`"
                            @click="contactBeingDeleted = contact"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
                <a
                    v-if="contact.email"
                    :href="`mailto:${contact.email}`"
                    class="flex items-center gap-2 hover:underline"
                >
                    <Mail class="size-4 text-muted-foreground" />
                    {{ contact.email }}
                </a>
                <a
                    v-if="contact.phone"
                    :href="`tel:${contact.phone}`"
                    class="flex items-center gap-2 hover:underline"
                >
                    <Phone class="size-4 text-muted-foreground" />
                    {{ contact.phone }}
                </a>
            </li>
        </ul>
    </section>

    <ContactDialog
        v-if="isAdding"
        :supplier-id="supplierId"
        :open="true"
        @update:open="isAdding = false"
    />

    <ContactDialog
        v-if="contactBeingEdited"
        :supplier-id="supplierId"
        :contact="contactBeingEdited"
        :open="true"
        @update:open="contactBeingEdited = null"
    />

    <ConfirmActionDialog
        v-if="contactBeingDeleted"
        :action="
            ContactController.destroy.form({
                supplier: supplierId,
                contact: contactBeingDeleted.id,
            })
        "
        :title="`Delete ${contactBeingDeleted.name}?`"
        description="Their contact details are removed permanently."
        confirm-label="Delete"
        :open="true"
        @update:open="contactBeingDeleted = null"
    />
</template>
