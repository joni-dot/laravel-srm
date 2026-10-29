<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import CategoryDialog from '@/components/categories/CategoryDialog.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { Category } from '@/types';

type CategoryWithCount = Category & { suppliers_count: number };

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Categories', href: CategoryController.index() },
        ],
    },
});

defineProps<{
    categories: CategoryWithCount[];
}>();

const isAdding = ref(false);
const categoryBeingRenamed = ref<CategoryWithCount | null>(null);
const categoryBeingDeleted = ref<CategoryWithCount | null>(null);

function supplierCount(category: CategoryWithCount): string {
    return category.suppliers_count === 1
        ? '1 supplier'
        : `${category.suppliers_count} suppliers`;
}
</script>

<template>
    <Head title="Categories" />

    <div class="flex max-w-3xl flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Categories"
                description="The list buyers choose from when describing what a supplier provides"
                class="mb-0"
            />
            <Button @click="isAdding = true"><Plus /> Add category</Button>
        </div>

        <p v-if="categories.length === 0" class="text-sm text-muted-foreground">
            No categories yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="category in categories"
                :key="category.id"
                class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
            >
                <div>
                    <p class="font-medium">{{ category.name }}</p>
                    <p class="text-muted-foreground">
                        {{ supplierCount(category) }}
                    </p>
                </div>
                <div class="flex">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Rename ${category.name}`"
                        @click="categoryBeingRenamed = category"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Delete ${category.name}`"
                        @click="categoryBeingDeleted = category"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>
    </div>

    <CategoryDialog
        v-if="isAdding"
        :open="true"
        @update:open="isAdding = false"
    />

    <CategoryDialog
        v-if="categoryBeingRenamed"
        :category="categoryBeingRenamed"
        :open="true"
        @update:open="categoryBeingRenamed = null"
    />

    <ConfirmActionDialog
        v-if="categoryBeingDeleted"
        :action="CategoryController.destroy.form(categoryBeingDeleted.id)"
        :title="`Delete ${categoryBeingDeleted.name}?`"
        :description="`It is removed from ${supplierCount(categoryBeingDeleted)}. The suppliers themselves are kept.`"
        confirm-label="Delete"
        :open="true"
        @update:open="categoryBeingDeleted = null"
    />
</template>
