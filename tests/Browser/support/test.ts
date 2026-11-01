import { expect, test as base } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Every test also fails on a JavaScript error or a Content Security Policy
 * violation, because the browser reports both as errors.
 */
export const test = base.extend({
    page: async ({ page }, use) => {
        const browserErrors: string[] = [];

        page.on('pageerror', (error) => browserErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') {
                browserErrors.push(message.text());
            }
        });

        await use(page);

        expect(browserErrors).toEqual([]);
    },
});

export { expect };

let supplierCount = 0;

/** Creates an active supplier through the form and stays on its page. */
export async function createSupplier(
    page: Page,
    namePrefix: string,
): Promise<string> {
    supplierCount += 1;
    const name = `${namePrefix} ${supplierCount}`;

    await page.goto('/suppliers/create');
    await page.getByLabel('Name', { exact: true }).fill(name);
    await page.getByLabel('Status').selectOption({ label: 'Active' });
    await page.getByRole('button', { name: 'Create supplier' }).click();
    await expect(page.getByRole('heading', { name })).toBeVisible();

    return name;
}

/** A date this many days from today, as a date field expects it. */
export function daysFromToday(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return date.toISOString().slice(0, 10);
}
