<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArchiveRestore, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import RatingCriterionController from '@/actions/App/Http/Controllers/Admin/RatingCriterionController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import RatingCriterionDialog from '@/components/ratings/RatingCriterionDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type CriterionRow = {
    id: number;
    name: string;
    retired_at: string | null;
    scores_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Rating criteria',
                href: RatingCriterionController.index(),
            },
        ],
    },
});

defineProps<{
    criteria: CriterionRow[];
}>();

const isAdding = ref(false);
const criterionBeingRenamed = ref<CriterionRow | null>(null);
const criterionBeingRemoved = ref<CriterionRow | null>(null);

function usage(criterion: CriterionRow): string {
    return criterion.scores_count === 1
        ? 'Used in 1 rating'
        : `Used in ${criterion.scores_count} ratings`;
}

function removalDescription(criterion: CriterionRow): string {
    return criterion.scores_count > 0
        ? 'Old ratings keep it, so it is retired rather than deleted. New ratings will not use it. Overall scores of old ratings do not change.'
        : 'No ratings use it, so it is deleted permanently.';
}

function restore(criterion: CriterionRow): void {
    router.post(
        RatingCriterionController.restore.url(criterion.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Rating criteria" />

    <div class="flex max-w-3xl flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Rating criteria"
                description="What buyers score suppliers on, from 1 to 5"
                class="mb-0"
            />
            <Button @click="isAdding = true"><Plus /> Add criterion</Button>
        </div>

        <p v-if="criteria.length === 0" class="text-sm text-muted-foreground">
            No criteria yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="criterion in criteria"
                :key="criterion.id"
                class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
            >
                <div>
                    <p class="font-medium">
                        {{ criterion.name }}
                        <Badge
                            v-if="criterion.retired_at"
                            variant="outline"
                            class="ml-1"
                            >Retired</Badge
                        >
                    </p>
                    <p class="text-muted-foreground">{{ usage(criterion) }}</p>
                </div>
                <div class="flex">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Rename ${criterion.name}`"
                        @click="criterionBeingRenamed = criterion"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        v-if="criterion.retired_at"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Bring back ${criterion.name}`"
                        @click="restore(criterion)"
                    >
                        <ArchiveRestore />
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Remove ${criterion.name}`"
                        @click="criterionBeingRemoved = criterion"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>
    </div>

    <RatingCriterionDialog
        v-if="isAdding"
        :open="true"
        @update:open="isAdding = false"
    />

    <RatingCriterionDialog
        v-if="criterionBeingRenamed"
        :criterion="criterionBeingRenamed"
        :open="true"
        @update:open="criterionBeingRenamed = null"
    />

    <ConfirmActionDialog
        v-if="criterionBeingRemoved"
        :action="
            RatingCriterionController.destroy.form(criterionBeingRemoved.id)
        "
        :title="`Remove ${criterionBeingRemoved.name}?`"
        :description="removalDescription(criterionBeingRemoved)"
        confirm-label="Remove"
        :open="true"
        @update:open="criterionBeingRemoved = null"
    />
</template>
