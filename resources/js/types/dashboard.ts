import type { RiskLevel, SupplierStatus } from './suppliers';

export type RiskLevelCount = {
    level: RiskLevel;
    count: number;
};

export type SupplierStatusCount = {
    status: SupplierStatus;
    count: number;
};

export type DashboardAttention = {
    contracts_action_soon: number | null;
    missing_certificates: number;
    certificates_expiring: number;
    high_risk: number;
    rating_overdue: number;
};

export type UpcomingDeadline = {
    key: string;
    kind: 'contract_cancel_by' | 'contract_ends' | 'certificate_expires';
    date: string;
    title: string;
    contract_id: number | null;
    supplier_id: number;
    supplier_name: string;
};

export type TopSpend = {
    currency_code: string;
    suppliers: { id: number; name: string; amount: string }[];
};
