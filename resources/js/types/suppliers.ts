export type SupplierStatus = 'prospect' | 'active' | 'inactive' | 'blocked';

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
    categories: string[];
    contacts: Contact[];
};

export type SupplierFormData = {
    id: number;
    name: string;
    supplier_number: string | null;
    status: SupplierStatus;
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
    countries: SelectOption[];
    currencies: SelectOption[];
    deliveryTerms: SelectOption[];
    categories: Category[];
};
