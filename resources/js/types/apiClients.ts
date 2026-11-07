export type ApiScopeOption = {
    value: string;
    label: string;
    description: string;
};

export type ApiClientRow = {
    id: string;
    name: string;
    scopes: string[];
    responsible_user_id: number | null;
    responsible_user_name: string | null;
    revoked: boolean;
    last_used_at: string | null;
    created_at: string;
};

export type NewApiClientSecret = {
    client_id: string;
    name: string;
    secret: string;
};
