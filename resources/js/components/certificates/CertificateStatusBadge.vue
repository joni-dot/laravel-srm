<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import type { CertificateStatus } from '@/types';

defineProps<{
    status: CertificateStatus;
}>();

const appearance: Record<
    CertificateStatus,
    { label: string; variant: BadgeVariants['variant'] }
> = {
    valid: { label: 'Valid', variant: 'secondary' },
    expiring_soon: { label: 'Expiring soon', variant: 'outline' },
    expired: { label: 'Expired', variant: 'destructive' },
};
</script>

<template>
    <Badge :variant="appearance[status].variant">
        {{ appearance[status].label }}
    </Badge>
</template>
