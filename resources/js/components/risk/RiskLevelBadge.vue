<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import type { RiskLevel } from '@/types';

defineProps<{
    level: RiskLevel;
}>();

const appearance: Record<
    RiskLevel,
    { label: string; variant: BadgeVariants['variant'] }
> = {
    low: { label: 'Low', variant: 'secondary' },
    medium: { label: 'Medium', variant: 'default' },
    high: { label: 'High', variant: 'destructive' },
};
</script>

<template>
    <Badge :variant="appearance[level].variant">
        {{ appearance[level].label }}
    </Badge>
</template>
