import { seeded } from '../support/test-data';
import { expect, test } from '../support/test';

// Reading the raw response also catches data that is sent to the browser but
// hidden on screen. tests/Browser/buyer/full-access.spec.ts proves these same
// values do appear for a buyer, so these checks cannot pass by accident.

for (const url of [seeded.supplierUrl, '/dashboard']) {
    test(`${url} never sends contract or spend data`, async ({ page }) => {
        const response = await page.request.get(url);
        const body = await response.text();

        expect(response.status()).toBe(200);
        expect(body).not.toContain(seeded.contractTitle);
        expect(body).not.toContain(seeded.contractValue);
        expect(body).not.toContain(seeded.spendAmount);
        expect(body).not.toContain(seeded.contractComment);
    });
}

test('the supplier page shows a contract count and no editing', async ({
    page,
}) => {
    await page.goto(seeded.supplierUrl);

    await expect(
        page.getByRole('heading', { name: seeded.supplierName }),
    ).toBeVisible();
    await expect(
        page.getByText(
            '1 contract. Only buyers and admins can see the details.',
        ),
    ).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Spend' })).toHaveCount(0);

    await expect(page.getByText(seeded.supplierComment)).toBeVisible();
    await expect(page.getByLabel('New comment')).toHaveCount(0);

    for (const name of [
        'Edit',
        'Archive',
        'Add rating',
        'Add certificate',
        'Post comment',
        'Delete comment',
    ]) {
        await expect(page.getByRole('button', { name })).toHaveCount(0);
    }
    await expect(page.getByRole('link', { name: 'Edit' })).toHaveCount(0);
});

for (const url of [
    seeded.contractUrl,
    `${seeded.contractUrl}/edit`,
    `${seeded.supplierUrl}/edit`,
    `${seeded.supplierUrl}/contracts/create`,
    '/suppliers/create',
    '/admin/users',
    '/admin/settings',
]) {
    test(`${url} is forbidden`, async ({ page }) => {
        const response = await page.request.get(url);

        expect(response.status()).toBe(403);
    });
}
