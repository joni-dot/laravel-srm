<script setup lang="ts">
defineProps<{
    label: string;
    value?: string | null;
}>();
</script>

<template>
    <div class="space-y-1">
        <dt class="text-sm text-muted-foreground">{{ label }}</dt>
        <dd class="text-sm">
            <slot>{{ value || '—' }}</slot>
        </dd>
    </div>
</template>
