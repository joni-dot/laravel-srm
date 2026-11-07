<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Ban, KeyRound, Pencil, Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import ApiClientController from '@/actions/App/Http/Controllers/Admin/ApiClientController';
import RegenerateApiClientSecretController from '@/actions/App/Http/Controllers/Admin/RegenerateApiClientSecretController';
import RevokeApiClientController from '@/actions/App/Http/Controllers/Admin/RevokeApiClientController';
import ApiClientDialog from '@/components/api-clients/ApiClientDialog.vue';
import ApiClientSecretDialog from '@/components/api-clients/ApiClientSecretDialog.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import type { ApiClientRow, ApiScopeOption, NewApiClientSecret } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'API clients', href: ApiClientController.index() },
        ],
    },
});

const props = defineProps<{
    apiClients: ApiClientRow[];
    scopes: ApiScopeOption[];
    responsibleUsers: { id: number; name: string }[];
    tokenUrl: string;
    accessTokenMinutes: number;
}>();

const page = usePage();

const isAdding = ref(false);
const clientBeingEdited = ref<ApiClientRow | null>(null);
const clientGettingNewSecret = ref<ApiClientRow | null>(null);
const clientBeingRevoked = ref<ApiClientRow | null>(null);
const newCredentials = ref<NewApiClientSecret | null>(null);

// The server sends a new secret once, as flash data; it is kept only while the dialog is open.
watch(
    () => page.flash.apiClientSecret as NewApiClientSecret | undefined,
    (credentials) => {
        if (credentials) {
            newCredentials.value = credentials;
        }
    },
    { immediate: true },
);

function scopeLabels(client: ApiClientRow): string {
    return props.scopes
        .filter((scope) => client.scopes.includes(scope.value))
        .map((scope) => scope.label)
        .join(', ');
}
</script>

<template>
    <Head title="API clients" />

    <div class="flex max-w-4xl flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="API clients"
                :description="`Systems that read SRM data on their own. Each one trades its secret for an access token that lasts ${accessTokenMinutes} minutes.`"
                class="mb-0"
            />
            <Button @click="isAdding = true"><Plus /> Add API client</Button>
        </div>

        <p v-if="apiClients.length === 0" class="text-sm text-muted-foreground">
            No API clients yet.
        </p>

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="client in apiClients"
                :key="client.id"
                class="flex items-start justify-between gap-4 px-4 py-3 text-sm"
                :class="{ 'text-muted-foreground': client.revoked }"
            >
                <div class="min-w-0 space-y-1">
                    <p class="flex items-center gap-2 font-medium">
                        {{ client.name }}
                        <Badge v-if="client.revoked" variant="secondary">
                            Revoked
                        </Badge>
                    </p>
                    <p>
                        {{ scopeLabels(client) || 'No access' }}
                        <span v-if="client.responsible_user_name">
                            · Responsible: {{ client.responsible_user_name }}
                        </span>
                    </p>
                    <p class="text-muted-foreground">
                        Created {{ formatDateTime(client.created_at) }} · Last
                        used
                        {{
                            client.last_used_at
                                ? formatDateTime(client.last_used_at)
                                : 'never'
                        }}
                    </p>
                    <p class="truncate font-mono text-xs text-muted-foreground">
                        Client ID {{ client.id }}
                    </p>
                </div>
                <div v-if="!client.revoked" class="flex shrink-0">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Edit ${client.name}`"
                        @click="clientBeingEdited = client"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`New secret for ${client.name}`"
                        @click="clientGettingNewSecret = client"
                    >
                        <KeyRound />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Revoke ${client.name}`"
                        @click="clientBeingRevoked = client"
                    >
                        <Ban />
                    </Button>
                </div>
            </li>
        </ul>
    </div>

    <ApiClientDialog
        v-if="isAdding"
        :scopes="scopes"
        :responsible-users="responsibleUsers"
        :open="true"
        @update:open="isAdding = false"
    />

    <ApiClientDialog
        v-if="clientBeingEdited"
        :api-client="clientBeingEdited"
        :scopes="scopes"
        :responsible-users="responsibleUsers"
        :open="true"
        @update:open="clientBeingEdited = null"
    />

    <ConfirmActionDialog
        v-if="clientGettingNewSecret"
        :action="
            RegenerateApiClientSecretController.form(clientGettingNewSecret.id)
        "
        :title="`New secret for ${clientGettingNewSecret.name}?`"
        description="The old secret and its access tokens stop working at once. The system needs the new secret before it can connect again."
        confirm-label="Create new secret"
        :open="true"
        @update:open="clientGettingNewSecret = null"
    />

    <ConfirmActionDialog
        v-if="clientBeingRevoked"
        :action="RevokeApiClientController.form(clientBeingRevoked.id)"
        :title="`Revoke ${clientBeingRevoked.name}?`"
        description="The system loses API access at once, and this cannot be undone. The client stays in the list for the record."
        confirm-label="Revoke"
        :open="true"
        @update:open="clientBeingRevoked = null"
    />

    <ApiClientSecretDialog
        v-if="newCredentials"
        :credentials="newCredentials"
        :token-url="tokenUrl"
        :open="true"
        @update:open="newCredentials = null"
    />
</template>
