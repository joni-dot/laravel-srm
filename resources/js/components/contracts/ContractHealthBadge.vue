<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import type { ContractHealth } from '@/types';

defineProps<{
    health: ContractHealth;
}>();

const appearance: Record<
    ContractHealth,
    { label: string; variant: BadgeVariants['variant'] }
> = {
    draft: { label: 'Draft', variant: 'outline' },
    active: { label: 'Active', variant: 'secondary' },
    action_soon: { label: 'Action needed soon', variant: 'default' },
    expired: { label: 'Expired', variant: 'destructive' },
    terminated: { label: 'Terminated', variant: 'outline' },
};
</script>

<template>
    <Badge :variant="appearance[health].variant">
        {{ appearance[health].label }}
    </Badge>
</template>
