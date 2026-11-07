import { password } from '../support/test-data';
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

test('an admin can add an API client, see its secret once and revoke it', async ({
    page,
}) => {
    await page.goto('/admin/api-clients');
    await expect(
        page.getByRole('heading', { name: 'Confirm password' }),
    ).toBeVisible();
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await page.getByRole('button', { name: 'Confirm password' }).click();
    await expect(
        page.getByRole('heading', { name: 'API clients', exact: true }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Add API client' }).click();
    const form = page.getByRole('dialog');
    await form.getByLabel('Name').fill('Browser Test Integration');
    await form.getByLabel('Read suppliers').check();
    await form.getByLabel('Responsible person').selectOption({ index: 1 });
    await form.getByRole('button', { name: 'Save' }).click();

    const credentials = page.getByRole('dialog', {
        name: 'Credentials for Browser Test Integration',
    });
    await expect(
        credentials.getByRole('textbox', { name: 'Client secret' }),
    ).toHaveValue(/^[A-Za-z0-9]{40}$/);
    await credentials
        .getByRole('button', { name: 'Copy Client secret' })
        .click();
    await credentials.getByRole('button', { name: 'Done' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);

    await page.reload();
    await expect(page.getByText('Browser Test Integration')).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);

    await page
        .getByRole('button', { name: 'Revoke Browser Test Integration' })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Revoke' })
        .click();
    await expect(page.getByText('Revoked', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Revoke Browser Test Integration' }),
    ).toHaveCount(0);
});
