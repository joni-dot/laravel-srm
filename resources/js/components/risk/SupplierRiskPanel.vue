<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import RiskOverrideController from '@/actions/App/Http/Controllers/Suppliers/RiskOverrideController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import RiskLevelBadge from '@/components/risk/RiskLevelBadge.vue';
import RiskOverrideDialog from '@/components/risk/RiskOverrideDialog.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import type { SelectOption, SupplierRiskAssessment } from '@/types';

defineProps<{
    supplierId: number;
    risk: SupplierRiskAssessment;
    riskLevels: SelectOption[];
    canEdit: boolean;
}>();

const isChanging = ref(false);
const isClearing = ref(false);
</script>

<template>
    <section
        class="space-y-3 rounded-xl border p-6"
        aria-labelledby="risk-heading"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Heading id="risk-heading" variant="small" title="Risk level" />
                <RiskLevelBadge :level="risk.level" />
            </div>
            <div v-if="canEdit" class="flex gap-2">
                <Button
                    v-if="risk.is_overridden"
                    variant="ghost"
                    size="sm"
                    @click="isClearing = true"
                >
                    Use calculated level
                </Button>
                <Button variant="outline" size="sm" @click="isChanging = true">
                    Change risk level
                </Button>
            </div>
        </div>

        <div v-if="risk.is_overridden" class="space-y-1 text-sm">
            <p class="text-muted-foreground">
                Set by {{ risk.overridden_by ?? 'a former user' }}
                <template v-if="risk.overridden_at">
                    on {{ formatDate(risk.overridden_at) }}</template
                >. The calculated level is
                <span class="font-medium">{{ risk.calculated_level }}</span
                >.
            </p>
            <p class="whitespace-pre-line">{{ risk.reason }}</p>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            Calculated from the supplier's status, latest rating and
            certificates.
        </p>

        <ul v-if="risk.signals.length > 0" class="space-y-1 text-sm">
            <li
                v-for="signal in risk.signals"
                :key="signal.message"
                class="flex items-center gap-2"
            >
                <TriangleAlert class="size-4 shrink-0 text-muted-foreground" />
                {{ signal.message }}
            </li>
        </ul>
    </section>

    <RiskOverrideDialog
        v-if="isChanging"
        :supplier-id="supplierId"
        :current-level="risk.level"
        :current-reason="risk.reason"
        :risk-levels="riskLevels"
        :open="true"
        @update:open="isChanging = false"
    />

    <ConfirmActionDialog
        v-if="isClearing"
        :action="RiskOverrideController.destroy.form(supplierId)"
        title="Use the calculated level?"
        description="The manually set level and its reason are removed."
        confirm-label="Use calculated level"
        :open="true"
        @update:open="isClearing = false"
    />
</template>
