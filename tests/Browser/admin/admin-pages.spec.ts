import { expect, test } from '../support/test';

const pages = {
    '/admin/users': 'Users',
    '/admin/categories': 'Categories',
    '/admin/certificate-types': 'Certificate types',
    '/admin/contract-types': 'Contract types',
    '/admin/rating-criteria': 'Rating criteria',
    '/admin/settings': 'Settings',
};

for (const [url, title] of Object.entries(pages)) {
    test(`${url} opens without browser errors`, async ({ page }) => {
        await page.goto(url);

        await expect(
            page.getByRole('heading', { name: title, exact: true }),
        ).toBeVisible();
    });
}

test('an admin can add a category', async ({ page }) => {
    await page.goto('/admin/categories');

    await page.getByRole('button', { name: 'Add category' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill('Browser Test Category');
    await dialog.getByRole('button', { name: 'Save' }).click();

    await expect(dialog).toHaveCount(0);
    await expect(page.getByText('Browser Test Category')).toBeVisible();
});
