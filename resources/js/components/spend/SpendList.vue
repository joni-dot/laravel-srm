<script setup lang="ts">
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import SpendController from '@/actions/App/Http/Controllers/Suppliers/SpendController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import SpendDialog from '@/components/spend/SpendDialog.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import type { SelectOption, SupplierSpend } from '@/types';

defineProps<{
    supplierId: number;
    spends: SupplierSpend[];
    currencies: SelectOption[];
    canEdit: boolean;
}>();

const isAdding = ref(false);
const spendBeingEdited = ref<SupplierSpend | null>(null);
const spendBeingDeleted = ref<SupplierSpend | null>(null);
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <Heading variant="small" title="Spend" />
            <Button
                v-if="canEdit"
                variant="outline"
                size="sm"
                @click="isAdding = true"
            >
                <Plus /> Add spend
            </Button>
        </div>

        <p v-if="spends.length === 0" class="text-sm text-muted-foreground">
            No spend recorded yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="spend in spends"
                :key="spend.id"
                class="flex items-center justify-between gap-4 px-4 py-2 text-sm"
            >
                <p>
                    <span class="font-medium">{{ spend.year }}</span>
                    <span class="ml-4">{{
                        formatMoney(spend.amount, spend.currency_code)
                    }}</span>
                </p>
                <div v-if="canEdit" class="flex">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Edit spend for ${spend.year}`"
                        @click="spendBeingEdited = spend"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Delete spend for ${spend.year}`"
                        @click="spendBeingDeleted = spend"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>
    </section>

    <SpendDialog
        v-if="isAdding"
        :supplier-id="supplierId"
        :currencies="currencies"
        :open="true"
        @update:open="isAdding = false"
    />

    <SpendDialog
        v-if="spendBeingEdited"
        :supplier-id="supplierId"
        :currencies="currencies"
        :spend="spendBeingEdited"
        :open="true"
        @update:open="spendBeingEdited = null"
    />

    <ConfirmActionDialog
        v-if="spendBeingDeleted"
        :action="
            SpendController.destroy.form({
                supplier: supplierId,
                spend: spendBeingDeleted.id,
            })
        "
        :title="`Delete spend for ${spendBeingDeleted.year}?`"
        description="The amount is removed permanently."
        confirm-label="Delete"
        :open="true"
        @update:open="spendBeingDeleted = null"
    />
</template>
