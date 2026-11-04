<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CommentController from '@/actions/App/Http/Controllers/Suppliers/CommentController';
import CommentItem from '@/components/comments/CommentItem.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { Comment, CommentFeed } from '@/types';

/**
 * Comments about a supplier, or about one contract when contractId is given.
 */
const props = defineProps<{
    supplierId: number;
    feed: CommentFeed;
    canComment: boolean;
    contractId?: number;
}>();

const commentBeingDeleted = ref<Comment | null>(null);

function showOlder(): void {
    router.reload({
        only: ['comments'],
        data: { comments: props.feed.next_limit },
    });
}
</script>

<template>
    <section class="space-y-4">
        <Heading variant="small" title="Comments" />

        <Button
            v-if="feed.next_limit"
            variant="outline"
            size="sm"
            @click="showOlder"
        >
            Show older comments
        </Button>

        <p v-if="feed.items.length === 0" class="text-sm text-muted-foreground">
            No comments yet.
        </p>

        <ul v-else class="space-y-3">
            <CommentItem
                v-for="comment in feed.items"
                :key="`${comment.id}-${comment.edited_at}`"
                :supplier-id="supplierId"
                :comment="comment"
                @delete="commentBeingDeleted = comment"
            />
        </ul>

        <Form
            v-if="canComment"
            v-bind="CommentController.store.form(supplierId)"
            :reset-on-success="['body']"
            :options="{ preserveScroll: true }"
            class="space-y-3"
            v-slot="{ errors, processing }"
        >
            <input
                v-if="contractId"
                type="hidden"
                name="contract_id"
                :value="contractId"
            />
            <FormField
                id="comment-body"
                label="New comment"
                :error="errors.body"
            >
                <Textarea
                    id="comment-body"
                    name="body"
                    rows="3"
                    maxlength="5000"
                    required
                />
            </FormField>
            <InputError :message="errors.contract_id" />

            <div class="flex justify-end">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Post comment
                </Button>
            </div>
        </Form>
    </section>

    <ConfirmActionDialog
        v-if="commentBeingDeleted"
        :action="
            CommentController.destroy.form({
                supplier: supplierId,
                comment: commentBeingDeleted.id,
            })
        "
        title="Delete this comment?"
        description="The comment is removed permanently."
        confirm-label="Delete"
        :open="true"
        @update:open="commentBeingDeleted = null"
    />
</template>
