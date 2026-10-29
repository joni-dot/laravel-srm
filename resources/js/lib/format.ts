/**
 * Shows a date-only value such as "2026-10-29" in the user's own date format.
 * The parts are read one by one so the date never shifts because of time zones.
 */
export function formatDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString();
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
