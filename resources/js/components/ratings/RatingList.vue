<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import RatingController from '@/actions/App/Http/Controllers/Suppliers/RatingController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import RatingDialog from '@/components/ratings/RatingDialog.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import type { RatingCriterion, SupplierRating } from '@/types';

defineProps<{
    supplierId: number;
    latestScore: string | null;
    ratings: SupplierRating[];
    criteria: RatingCriterion[];
    canEdit: boolean;
}>();

const isAdding = ref(false);
const ratingBeingDeleted = ref<SupplierRating | null>(null);
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                variant="small"
                title="Ratings"
                :description="
                    latestScore
                        ? `Latest overall score: ${latestScore} out of 5`
                        : undefined
                "
            />
            <Button
                v-if="canEdit && criteria.length > 0"
                variant="outline"
                size="sm"
                @click="isAdding = true"
            >
                <Plus /> Add rating
            </Button>
        </div>

        <p v-if="ratings.length === 0" class="text-sm text-muted-foreground">
            No ratings yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="rating in ratings"
                :key="rating.id"
                class="flex items-start justify-between gap-4 px-4 py-3 text-sm"
            >
                <div class="space-y-1">
                    <p>
                        <span class="font-medium"
                            >{{ rating.overall_score }} out of 5</span
                        >
                        <span class="text-muted-foreground">
                            · {{ formatDate(rating.rated_on) }}
                            <template v-if="rating.rated_by">
                                · {{ rating.rated_by }}</template
                            >
                        </span>
                    </p>
                    <p class="text-muted-foreground">
                        {{
                            rating.scores
                                .map(
                                    ({ criterion, score }) =>
                                        `${criterion} ${score}`,
                                )
                                .join(', ')
                        }}
                    </p>
                    <p v-if="rating.comment" class="whitespace-pre-line">
                        {{ rating.comment }}
                    </p>
                </div>
                <Button
                    v-if="canEdit"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Delete rating from ${formatDate(rating.rated_on)}`"
                    @click="ratingBeingDeleted = rating"
                >
                    <Trash2 />
                </Button>
            </li>
        </ul>
    </section>

    <RatingDialog
        v-if="isAdding"
        :supplier-id="supplierId"
        :criteria="criteria"
        :open="true"
        @update:open="isAdding = false"
    />

    <ConfirmActionDialog
        v-if="ratingBeingDeleted"
        :action="
            RatingController.destroy.form({
                supplier: supplierId,
                rating: ratingBeingDeleted.id,
            })
        "
        title="Delete this rating?"
        description="It is removed permanently, and the supplier's latest score and risk level are updated."
        confirm-label="Delete"
        :open="true"
        @update:open="ratingBeingDeleted = null"
    />
</template>
