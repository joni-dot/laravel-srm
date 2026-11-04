<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CommentController from '@/actions/App/Http/Controllers/Suppliers/CommentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import type { Comment } from '@/types';

const props = defineProps<{
    supplierId: number;
    comment: Comment;
}>();

defineEmits<{ delete: [] }>();

const isEditing = ref(false);
const draft = ref(props.comment.body);

function startEditing(): void {
    draft.value = props.comment.body;
    isEditing.value = true;
}
</script>

<template>
    <li
        class="space-y-2 rounded-2xl rounded-tl-sm border bg-muted/40 px-4 py-3 text-sm"
    >
        <div class="flex items-start justify-between gap-4">
            <p>
                <span class="font-medium">{{ comment.author }}</span>
                <span class="text-muted-foreground">
                    ·
                    <time :datetime="comment.created_at">{{
                        formatDateTime(comment.created_at)
                    }}</time>
                    <template v-if="comment.edited_at"> · edited</template>
                </span>
            </p>

            <div v-if="!isEditing" class="flex gap-1">
                <Button
                    v-if="comment.can.update"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Edit comment"
                    @click="startEditing"
                >
                    <Pencil />
                </Button>
                <Button
                    v-if="comment.can.delete"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Delete comment"
                    @click="$emit('delete')"
                >
                    <Trash2 />
                </Button>
            </div>
        </div>

        <Form
            v-if="isEditing"
            v-bind="
                CommentController.update.form({
                    supplier: supplierId,
                    comment: comment.id,
                })
            "
            :options="{ preserveScroll: true }"
            class="space-y-2"
            @success="isEditing = false"
            v-slot="{ errors, processing }"
        >
            <Textarea
                v-model="draft"
                name="body"
                class="bg-background"
                aria-label="Comment"
                rows="3"
                maxlength="5000"
                required
            />
            <InputError :message="errors.body" />
            <div class="flex gap-2">
                <Button type="submit" size="sm" :disabled="processing">
                    <Spinner v-if="processing" />
                    Save
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    @click="isEditing = false"
                >
                    Cancel
                </Button>
            </div>
        </Form>
        <p v-else class="break-words whitespace-pre-line">{{ comment.body }}</p>
    </li>
</template>
