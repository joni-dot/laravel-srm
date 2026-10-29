<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import type { SupplierStatus } from '@/types';

defineProps<{
    status: SupplierStatus;
}>();

const appearance: Record<
    SupplierStatus,
    { label: string; variant: BadgeVariants['variant'] }
> = {
    prospect: { label: 'Prospect', variant: 'outline' },
    active: { label: 'Active', variant: 'secondary' },
    inactive: { label: 'Inactive', variant: 'outline' },
    blocked: { label: 'Blocked', variant: 'destructive' },
};
</script>

<template>
    <Badge :variant="appearance[status].variant">
        {{ appearance[status].label }}
    </Badge>
</template>
