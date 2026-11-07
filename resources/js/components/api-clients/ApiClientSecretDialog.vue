<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
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
import { Label } from '@/components/ui/label';
import type { NewApiClientSecret } from '@/types';

defineProps<{
    credentials: NewApiClientSecret;
    tokenUrl: string;
}>();

const isOpen = defineModel<boolean>('open', { required: true });

const copiedField = ref<string | null>(null);

// The clipboard is unavailable on plain HTTP and can be refused; the text is then selected for copying by hand.
async function copy(field: string, value: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(value);
        copiedField.value = field;
    } catch {
        const input = document.getElementById(field) as HTMLInputElement | null;
        input?.focus();
        input?.select();
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Credentials for {{ credentials.name }}</DialogTitle
                >
                <DialogDescription>
                    Copy the secret now and store it in the other system. It is
                    not shown again; if it is lost, create a new secret.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div
                    v-for="field in [
                        {
                            id: 'token-url',
                            label: 'Token URL',
                            value: tokenUrl,
                        },
                        {
                            id: 'client-id',
                            label: 'Client ID',
                            value: credentials.client_id,
                        },
                        {
                            id: 'client-secret',
                            label: 'Client secret',
                            value: credentials.secret,
                        },
                    ]"
                    :key="field.id"
                    class="grid gap-2"
                >
                    <Label :for="field.id">{{ field.label }}</Label>
                    <div class="flex gap-2">
                        <Input
                            :id="field.id"
                            :model-value="field.value"
                            readonly
                            class="font-mono text-xs"
                            @focus="
                                ($event.target as HTMLInputElement).select()
                            "
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            :aria-label="`Copy ${field.label}`"
                            @click="copy(field.id, field.value)"
                        >
                            <Check v-if="copiedField === field.id" />
                            <Copy v-else />
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button">Done</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
