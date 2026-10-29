export type SupplierStatus = 'prospect' | 'active' | 'inactive' | 'blocked';

export type CertificateStatus = 'valid' | 'expiring_soon' | 'expired';

export type SelectOption = {
    value: string;
    label: string;
};

export type Category = {
    id: number;
    name: string;
};

export type SupplierListItem = {
    id: number;
    name: string;
    supplier_number: string | null;
    status: SupplierStatus;
    country: string;
    city: string | null;
    categories: string[];
    missing_certificates: string[];
};

export type Certificate = {
    id: number;
    certificate_type_id: number;
    type: string;
    reference: string | null;
    issued_on: string | null;
    expires_on: string | null;
    status: CertificateStatus;
    reminder_days: number[];
    file_name: string | null;
    file_size: number | null;
};

export type Contact = {
    id: number;
    name: string;
    job_title: string | null;
    email: string | null;
    phone: string | null;
    is_primary: boolean;
};

export type SupplierDetails = {
    id: number;
    name: string;
    supplier_number: string | null;
    status: SupplierStatus;
    country: string;
    business_id: string | null;
    vat_number: string | null;
    street_address: string | null;
    postal_code: string | null;
    city: string | null;
    website: string | null;
    email: string | null;
    phone: string | null;
    payment_terms: string | null;
    currency: string | null;
    delivery_term: string | null;
    delivery_place: string | null;
    notes: string | null;
    archived_at: string | null;
    responsible_user: string | null;
    categories: string[];
    contacts: Contact[];
    certificates: Certificate[];
    missing_certificates: string[];
};

export type SupplierFormData = {
    id: number;
    name: string;
    supplier_number: string | null;
    status: SupplierStatus;
    responsible_user_id: number | null;
    country_code: string;
    business_id: string | null;
    vat_number: string | null;
    street_address: string | null;
    postal_code: string | null;
    city: string | null;
    website: string | null;
    email: string | null;
    phone: string | null;
    payment_terms: string | null;
    currency_code: string | null;
    delivery_term: string | null;
    delivery_place: string | null;
    notes: string | null;
    category_ids: number[];
};

export type SupplierFormOptions = {
    statuses: SelectOption[];
    responsibleUsers: { id: number; name: string }[];
    countries: SelectOption[];
    currencies: SelectOption[];
    deliveryTerms: SelectOption[];
    categories: Category[];
};

export type CertificateType = {
    id: number;
    name: string;
};
